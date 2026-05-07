<?php
// tests/test_util.php — Unit tests for includes/util.php pure helpers.
// Skipped: column_exists / table_exists / index_exists / fetch_settings /
// audit_log all touch the DB and are integration concerns.

declare(strict_types=1);

require_once __DIR__ . '/harness.php';

// util.php require_onces db.php which require_onces config.php. Both are safe
// to load here — config.php only defines constants, db.php only declares the
// db() function (no connection happens until it's called).
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/util.php';

T::group('csv_to_array', function () {
    T::eq([],                    csv_to_array(null),              'null → []');
    T::eq([],                    csv_to_array(''),                'empty string → []');
    T::eq(['a'],                 csv_to_array('a'),               'single token');
    T::eq(['a', 'b', 'c'],       csv_to_array('a,b,c'),           'simple csv');
    T::eq(['a', 'b'],            csv_to_array(' a , b '),         'trims whitespace');
    T::eq(['a', 'b'],            csv_to_array('a,,b'),            'drops empty middle entries');
    T::eq(['a'],                 csv_to_array(',a,'),             'drops leading/trailing commas');
    T::eq(['a b', 'c d'],        csv_to_array('a b, c d'),        'preserves intra-token spaces');
    T::eq([],                    csv_to_array(' , , '),           'whitespace-only csv → []');
    // Returned arrays must always be 0-indexed lists. csv_to_array applies
    // array_filter (which preserves keys), then array_values (which reindexes).
    T::true(array_is_list(csv_to_array(',a,,b,')), 'result is a list (0-indexed)');
});

T::group('string_list', function () {
    T::eq([],            string_list(null),                          'null → []');
    T::eq([],            string_list('not-an-array'),                'scalar → []');
    T::eq(['a', 'b'],    string_list(['a', 'b']),                    'happy path');
    T::eq(['a'],         string_list(['  a  ']),                     'trims');
    T::eq(['a'],         string_list(['', '   ', 'a']),              'drops empty/whitespace');
    T::eq(['a'],         string_list(['a', 123, true, null, []]),    'drops non-string entries silently');
    T::eq(2,             count(string_list(array_fill(0, 100, 'x'), 2)), 'respects maxItems cap');
});

T::group('clean_url', function () {
    T::eq(null,                   clean_url(null),                          'null → null');
    T::eq(null,                   clean_url(''),                            'empty → null');
    T::eq(null,                   clean_url('   '),                         'whitespace → null');
    T::eq('https://example.com',  clean_url('https://example.com'),         'valid https');
    T::eq('http://example.com',   clean_url('http://example.com'),          'valid http');
    T::eq('https://example.com',  clean_url('  https://example.com  '),     'trims');
    T::eq(null,                   clean_url('not a url'),                   'rejects non-url');
    T::eq(null,                   clean_url(str_repeat('a', 600)),          'rejects > 500 chars');

    // No-scheme rejects (FILTER_VALIDATE_URL requires "scheme://").
    T::eq(null, clean_url('javascript:alert(1)'),                'rejects javascript: scheme');
    T::eq(null, clean_url('data:text/html,<script>alert(1)</script>'), 'rejects data: scheme');
    T::eq(null, clean_url('vbscript:msgbox(1)'),                  'rejects vbscript: scheme');

    // Scheme whitelist: only http and https survive. Stored URLs land in
    // href/src attributes, so anything outside http(s) is unintended.
    T::eq(null, clean_url('ftp://example.com'),                   'rejects ftp scheme');
    T::eq(null, clean_url('file:///etc/passwd'),                  'rejects file scheme');
    T::eq(null, clean_url('gopher://example.com'),                'rejects gopher scheme');

    // Mixed-case scheme is normalized via strtolower in the check.
    T::eq('HTTPS://example.com', clean_url('HTTPS://example.com'),
        'preserves caller casing while accepting uppercase scheme');
});

T::group('clean_image_src', function () {
    // Empty / null / oversized
    T::eq(null, clean_image_src(null),                     'null → null');
    T::eq(null, clean_image_src(''),                       'empty → null');
    T::eq(null, clean_image_src('   '),                    'whitespace → null');
    T::eq(null, clean_image_src(str_repeat('a', 600)),     'rejects > 500 chars');

    // Root-relative upload paths — what api/uploads/index.php returns. This
    // is the bug-fix path: clean_url() previously rejected these because
    // FILTER_VALIDATE_URL requires a scheme, silently NULLing cover_image
    // on every post save.
    T::eq('/uploads/projects/abc.png', clean_image_src('/uploads/projects/abc.png'),
        'accepts /uploads/ root-relative path');
    T::eq('/uploads/projects/sub/dir/x.webp', clean_image_src('/uploads/projects/sub/dir/x.webp'),
        'accepts nested upload path');
    T::eq('/uploads/projects/abc-123_v2.png', clean_image_src('  /uploads/projects/abc-123_v2.png  '),
        'trims and accepts hyphens/underscores/digits');

    // Reject traversal and weird chars even under /uploads/.
    T::eq(null, clean_image_src('/uploads/../etc/passwd'),    'rejects ../ traversal');
    T::eq(null, clean_image_src('/uploads/projects/../../x'), 'rejects nested ../');
    T::eq(null, clean_image_src('/uploads/projects/x;y.png'), 'rejects forbidden chars');
    T::eq(null, clean_image_src('/uploads/projects/x.png?q=1'), 'rejects query string');

    // Other root-relative paths fall through to the URL branch and fail.
    T::eq(null, clean_image_src('/etc/passwd'),               'rejects non-/uploads/ path');
    T::eq(null, clean_image_src('//proto-relative.example/x'), 'rejects protocol-relative URL');

    // External http(s) URLs still work — admin pasting an external image link.
    T::eq('https://cdn.example.com/x.png', clean_image_src('https://cdn.example.com/x.png'),
        'accepts external https');
    T::eq(null, clean_image_src('javascript:alert(1)'),       'rejects javascript: scheme');
    T::eq(null, clean_image_src('ftp://x/y.png'),             'rejects ftp: scheme');
});
