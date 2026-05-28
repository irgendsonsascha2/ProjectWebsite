<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/includes/request.php';
require_once dirname(__DIR__, 2).'/includes/input_validate.php';
require_once dirname(__DIR__, 2).'/includes/mongo_input_guard.php';

final class InputValidateTest extends TestCase
{
    public function test_strip_control_chars_removes_null_and_newline_when_disallowed(): void
    {
        $this->assertSame('ab', input_strip_control_chars("a\x00b\n", false));
    }

    public function test_bounded_text_rejects_empty_after_strip(): void
    {
        $this->assertNull(input_bounded_text("\x00\x1F", 100, false));
    }

    public function test_comment_text_respects_max_length(): void
    {
        $long = str_repeat('a', 500);
        $result = input_comment_text($long, 100);
        $this->assertNotNull($result);
        $this->assertSame(100, strlen($result));
    }

    public function test_slug_key_accepts_valid_and_rejects_invalid(): void
    {
        $this->assertSame('content_manager', input_slug_key('content_manager'));
        $this->assertNull(input_slug_key('1bad'));
        $this->assertNull(input_slug_key('has space'));
    }

    public function test_enum_strict(): void
    {
        $this->assertSame('like', input_enum('like', ['like', 'dislike']));
        $this->assertNull(input_enum('LIKE', ['like', 'dislike']));
    }

    public function test_interaction_type(): void
    {
        $this->assertSame('dislike', input_interaction_type('dislike'));
        $this->assertNull(input_interaction_type('love'));
    }

    public function test_project_tags_whitelist(): void
    {
        $tags = input_project_tags('Foo-Bar, bad tag!, valid_tag');
        $this->assertContains('valid_tag', $tags);
        $this->assertNotContains('bad tag!', $tags);
    }

    public function test_db_script_basename_requires_existing_file(): void
    {
        $this->assertSame(
            '17_db_init_mongo_read_views.php',
            input_db_script_basename('17_db_init_mongo_read_views.php')
        );
        $this->assertNull(input_db_script_basename('99_nonexistent_script.php'));
        $this->assertNull(input_db_script_basename('../03_db_init_mongo_roles.php'));
    }

    public function test_secret_password_rejects_nul(): void
    {
        $this->assertNull(input_secret_password("pass\x00word", 8));
        $this->assertSame('password1', input_secret_password('password1', 8));
    }

    public function test_user_password_allows_unicode_without_trim(): void
    {
        $pw = '  🔒über-lang-unicode-pass  ';
        $this->assertSame($pw, input_user_password($pw, 12, 512));
        $this->assertNull(input_user_password(str_repeat('a', 513), 12, 512));
    }

    public function test_normalize_permission_keys_uses_values_not_assoc_keys(): void
    {
        $allowed = ['view_projects', 'delete_all'];
        // Legacy attack shape: permission names as array keys — must not grant rights.
        $crafted = ['delete_all' => '1', 'view_projects' => 'on'];
        $this->assertSame([], normalize_permission_keys($crafted, $allowed));

        $fromCheckbox = normalize_permission_keys(['view_projects', 'delete_all'], $allowed);
        $this->assertSame(['view_projects', 'delete_all'], $fromCheckbox);
    }

    public function test_mongo_guard_is_safe_key(): void
    {
        $this->assertFalse(mongo_guard_is_safe_key('$gt'));
        $this->assertFalse(mongo_guard_is_safe_key('a.b'));
        $this->assertTrue(mongo_guard_is_safe_key('role_key'));
    }

    public function test_object_id_hex(): void
    {
        $this->assertNull(input_object_id_hex('not-valid'));
        $valid = '507f1f77bcf86cd799439011';
        $this->assertSame($valid, input_object_id_hex($valid));
    }
}
