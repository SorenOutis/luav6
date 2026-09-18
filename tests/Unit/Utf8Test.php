<?php

use App\Support\Utf8;

test('clean leaves valid UTF-8 untouched', function () {
    $value = "Photosynthesis — énergie 🌱\tline one\nline two\r\nline three";

    expect(Utf8::clean($value))->toBe($value);
});

test('clean repairs malformed byte sequences', function () {
    // \xC3\x28 is an invalid 2-byte sequence; \x93/\x94 are Windows-1252
    // smart quotes — typical PdfParser output.
    $malformed = "Cell \xC3\x28 division \x93mitosis\x94";

    $result = Utf8::clean($malformed);

    expect(mb_check_encoding($result, 'UTF-8'))->toBeTrue();
    expect($result)->toContain('Cell')->toContain('division')->toContain('mitosis');

    // The exact failure from the bug report: json_encode must not throw.
    expect(json_encode($result, JSON_THROW_ON_ERROR))->toBeString();
});

test('clean strips null bytes and control characters but keeps whitespace', function () {
    $result = Utf8::clean("a\x00b\x01c\x07\x0Bd\x0Ce\x1Ff\x7Fg\th\ni\r\nj");

    expect($result)->toBe("abcdefg\th\ni\r\nj");
    expect(mb_check_encoding($result, 'UTF-8'))->toBeTrue();
});

test('clean handles null and empty input', function () {
    expect(Utf8::clean(null))->toBe('');
    expect(Utf8::clean(''))->toBe('');
});

test('cleanDeep recurses into arrays including keys', function () {
    $input = [
        "qu\xC3\x28iz" => [
            'text' => "What is \x93osmosis\x94?",
            'points' => 5,
            'is_correct' => true,
            'nested' => [['a' => "\x00bad"]],
        ],
    ];

    $result = Utf8::cleanDeep($input);

    foreach (array_keys($result) as $key) {
        expect(mb_check_encoding($key, 'UTF-8'))->toBeTrue();
    }

    expect(json_encode($result, JSON_THROW_ON_ERROR))->toBeString();

    $first = reset($result);
    expect($first['points'])->toBe(5);
    expect($first['is_correct'])->toBeTrue();
    expect($first['nested'][0]['a'])->toBe('bad');
});

test('cleanDeep passes through non-string scalars', function () {
    expect(Utf8::cleanDeep(42))->toBe(42);
    expect(Utf8::cleanDeep(4.2))->toBe(4.2);
    expect(Utf8::cleanDeep(true))->toBeTrue();
    expect(Utf8::cleanDeep(null))->toBe('');
});

test('isValid detects malformed strings', function () {
    expect(Utf8::isValid('plain text'))->toBeTrue();
    expect(Utf8::isValid(null))->toBeTrue();
    expect(Utf8::isValid("\xC3\x28"))->toBeFalse();
});
