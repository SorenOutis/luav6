import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const source = (path: string) =>
    readFileSync(join(process.cwd(), path), 'utf8');

describe('AI action human approval boundary', () => {
    it('shows exact before and after values behind an explicit approval click', () => {
        const card = source('resources/js/components/AiActionApprovalCard.vue');

        expect(card).toContain('FROM');
        expect(card).toContain('TO');
        expect(card).toContain('Review &amp; approve');
        expect(card).toContain('Approve & execute');
        expect(card).toContain('/approve`');
        expect(card).toContain('{ nonce: props.action.nonce }');
    });

    it('renders approval cards on both chat surfaces', () => {
        const chats = source('resources/js/pages/Chats.vue');
        const widget = source('resources/js/components/FloatingWidget.vue');

        expect(chats).toContain('AiActionApprovalCard');
        expect(chats).toContain("axios.get('/api/ai-actions'");
        expect(widget).toContain('AiActionApprovalCard');
        expect(widget).toContain("axios.get('/api/ai-actions'");
    });

    it('removes the model-controlled confirm flag from every write tool', () => {
        const tools = [
            'CreateExamTool.php',
            'UpdateExamTool.php',
            'PostAnnouncementTool.php',
            'CreateAssignmentTool.php',
            'GenerateExamQuestionsTool.php',
        ];

        for (const tool of tools) {
            const php = source(`app/Ai/Tools/${tool}`);
            expect(php).not.toContain("'confirm' =>");
            expect(php).not.toContain("$request['confirm']");
            expect(php).toContain('stageAction(');
        }
    });
});
