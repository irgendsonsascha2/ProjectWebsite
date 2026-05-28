<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/includes/request.php';
require_once dirname(__DIR__, 2).'/includes/input_validate.php';

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

    public function test_password_secret_rejects_newlines(): void
    {
        $this->assertNull(input_password_secret("pass\nword", 8));
        $this->assertSame('password1', input_password_secret('password1', 8));
    }

    public function test_object_id_hex(): void
    {
        $this->assertNull(input_object_id_hex('not-valid'));
        $valid = '507f1f77bcf86cd799439011';
        $this->assertSame($valid, input_object_id_hex($valid));
    }
}
