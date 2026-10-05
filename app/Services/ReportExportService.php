<?php

namespace App\Services;

use App\Models\AiUsageLog;
use App\Models\Exam;
use App\Models\ExamSet;
use App\Models\Grade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use ZipArchive;

class ReportExportService
{
    public const FORMATS = ['csv', 'xlsx', 'docx', 'pdf'];

    public function __construct(private readonly ExamAnswerReportService $answerReports) {}

    /** @return array{path: string, filename: string, mime: string, url: string, expires_at: string, rows: int} */
    public function export(array $payload, int $actorId, int $workspaceId): array
    {
        $format = strtolower((string) ($payload['format'] ?? ''));
        if (! in_array($format, self::FORMATS, true)) {
            throw new \InvalidArgumentException('Unsupported export format.');
        }

        [$headers, $rows, $title] = $this->dataset($payload, $workspaceId);
        $id = (string) Str::uuid7();
        $base = 'ai-reports/'.$workspaceId.'/'.$id;
        $extension = $format;
        $filename = Str::slug($title ?: 'ai-report').'-'.now()->format('Y-m-d-His').'.'.$extension;
        $path = $base.'/'.$filename;

        $contents = match ($format) {
            'csv' => $this->csv($headers, $rows),
            'xlsx' => $this->xlsx($headers, $rows, $title),
            'docx' => $this->docx($headers, $rows, $title),
            'pdf' => $this->pdf($headers, $rows, $title),
        };

        Storage::disk('local')->put($path, $contents);
        $expires = now()->addHours(24);
        $url = URL::temporarySignedRoute('ai-reports.download', $expires, [
            'workspace' => $workspaceId,
            'filename' => $filename,
            'actor' => $actorId,
            'report' => $id,
        ]);

        return [
            'path' => $path,
            'filename' => $filename,
            'mime' => $this->mime($format),
            'url' => $url,
            'expires_at' => $expires->toIso8601String(),
            'rows' => count($rows),
        ];
    }

    /** @return array{0: array<int, string>, 1: array<int, array<int, string>>, 2: string} */
    private function dataset(array $payload, int $workspaceId): array
    {
        $type = (string) ($payload['report_type'] ?? '');

        if ($type === 'exam_answers') {
            $exam = Exam::query()->withoutGlobalScope('workspace')
                ->whereKey((int) ($payload['exam_id'] ?? 0))
                ->where('workspace_id', $workspaceId)
                ->firstOrFail();
            $set = null;
            if (! empty($payload['set_id'])) {
                $set = ExamSet::query()->where('exam_id', $exam->id)->find((int) $payload['set_id']);
                abort_unless($set, 404, 'The selected exam set was not found.');
            }
            $report = $this->answerReports->build(
                $exam,
                (string) ($payload['mode'] ?? ExamAnswerReportService::MODE_STUDENTS),
                array_map('intval', (array) ($payload['student_ids'] ?? [])),
                (bool) ($payload['include_key'] ?? true),
                $set,
            );

            return [
                ['Section', 'Value'],
                $this->flatten($report),
                'Exam answers - '.$exam->title,
            ];
        }

        if ($type === 'grades') {
            $grades = Grade::query()->where('workspace_id', $workspaceId)
                ->with(['student:id,name', 'section:id,name'])
                ->when($payload['section_id'] ?? null, fn ($q, $id) => $q->where('section_id', (int) $id))
                ->when($payload['student_id'] ?? null, fn ($q, $id) => $q->where('user_id', (int) $id))
                ->orderBy('subject')->orderBy('period')->get();

            return [
                ['Student', 'Section', 'Subject', 'Period', 'Score', 'Max Score', 'Percentage', 'Remarks'],
                $grades->map(fn (Grade $grade): array => [
                    $grade->student?->name ?? 'Unknown',
                    $grade->section?->name ?? 'Unknown',
                    (string) $grade->subject,
                    (string) $grade->period,
                    (string) $grade->score,
                    (string) $grade->max_score,
                    (string) $grade->percentage,
                    (string) ($grade->remarks ?? ''),
                ])->all(),
                'Grades report',
            ];
        }

        if ($type === 'ai_usage') {
            $logs = AiUsageLog::query()->withoutGlobalScope('workspace')
                ->where('workspace_id', $workspaceId)
                ->latest('id')->limit(5000)->get();

            return [
                ['Date', 'Feature', 'Provider', 'Model', 'Input Tokens', 'Output Tokens', 'Estimated Cost Micros', 'Status'],
                $logs->map(fn (AiUsageLog $log): array => [
                    (string) $log->date,
                    (string) $log->source,
                    (string) $log->provider,
                    (string) $log->model,
                    (string) $log->input_tokens,
                    (string) $log->output_tokens,
                    (string) $log->estimated_cost_micros,
                    (string) ($log->status ?? 'completed'),
                ])->all(),
                'AI usage report',
            ];
        }

        throw new \InvalidArgumentException('Unsupported report type.');
    }

    /** @return array<int, array<int, string>> */
    private function flatten(mixed $value, string $prefix = ''): array
    {
        if (! is_array($value)) {
            return [[$prefix, $this->scalar($value)]];
        }

        $rows = [];
        foreach ($value as $key => $child) {
            $label = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($child)) {
                $rows = [...$rows, ...$this->flatten($child, $label)];
            } else {
                $rows[] = [$label, $this->scalar($child)];
            }
        }

        return $rows;
    }

    private function scalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        return is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function csv(array $headers, array $rows): string
    {
        $handle = fopen('php://memory', 'r+');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);
        return (string) $contents;
    }

    private function docx(array $headers, array $rows, string $title): string
    {
        $word = new PhpWord;
        $section = $word->addSection();
        $section->addTitle($title, 1);
        $section->addText('Generated by Echo on '.now()->format('Y-m-d H:i:s'));
        $table = $section->addTable(['borderSize' => 6, 'cellMargin' => 80]);
        $table->addRow();
        foreach ($headers as $header) {
            $table->addCell(2200)->addText((string) $header, ['bold' => true]);
        }
        foreach ($rows as $row) {
            $table->addRow();
            foreach ($row as $cell) {
                $table->addCell(2200)->addText(Str::limit((string) $cell, 2000, ''));
            }
        }
        $tmp = tempnam(sys_get_temp_dir(), 'echo-docx-');
        $writer = WordIOFactory::createWriter($word, 'Word2007');
        $writer->save($tmp);
        $contents = file_get_contents($tmp);
        @unlink($tmp);
        return (string) $contents;
    }

    private function xlsx(array $headers, array $rows, string $title): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('The ZIP extension is required for XLSX exports.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'echo-xlsx-');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.htmlspecialchars(Str::limit($title ?: 'Report', 31, ''), ENT_XML1).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ([$headers, ...$rows] as $rowIndex => $row) {
            $xml .= '<row r="'.($rowIndex + 1).'">';
            foreach (array_values($row) as $columnIndex => $value) {
                $ref = $this->columnName($columnIndex + 1).($rowIndex + 1);
                $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();
        $contents = file_get_contents($tmp);
        @unlink($tmp);
        return (string) $contents;
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $name = chr(65 + $remainder).$name;
            $number = intdiv($number - 1, 26);
        }
        return $name;
    }

    private function pdf(array $headers, array $rows, string $title): string
    {
        $lines = [$title, 'Generated by Echo on '.now()->format('Y-m-d H:i:s'), ''];
        foreach ([$headers, ...$rows] as $row) {
            $lines[] = implode(' | ', array_map(fn ($value): string => Str::limit(str_replace(["\r", "\n"], ' ', (string) $value), 160, ''), $row));
        }
        $lines = array_chunk($lines, 46);
        $objects = [];
        $kids = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [';
        foreach (array_keys($lines) as $index) {
            $pageObject = 4 + ($index * 2);
            $kids[] = $pageObject.' 0 R';
        }
        $objects[1] .= implode(' ', $kids).'] /Count '.count($kids).' >>';
        foreach ($lines as $index => $pageLines) {
            $pageObject = 4 + ($index * 2);
            $contentObject = $pageObject + 1;
            $objects[$pageObject - 1] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentObject.' 0 R >>';
            $stream = "BT /F1 9 Tf 40 760 Td 12 TL ";
            foreach ($pageLines as $line) {
                $stream .= '('.$this->pdfEscape($line).') Tj T* ';
            }
            $stream .= 'ET';
            $objects[$contentObject - 1] = '<< /Length '.strlen($stream).' >>\nstream\n'.$stream.'\nendstream';
        }
        $objects[2] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        return $this->assemblePdf($objects);
    }

    private function pdfEscape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: $value);
    }

    private function assemblePdf(array $objects): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[$index + 1] = strlen($pdf);
            $pdf .= ($index + 1).' 0 obj\n'.$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf('%010d 00000 n \n', $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1).' /Root 1 0 R >>\nstartxref\n'.$xref."\n%%EOF\n";
        return $pdf;
    }

    private function mime(string $format): string
    {
        return match ($format) {
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pdf' => 'application/pdf',
        };
    }
}
