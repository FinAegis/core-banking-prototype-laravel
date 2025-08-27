<?php

namespace Tests\Unit\Helpers;

use App\Helpers\SyntaxHighlighter;
use Highlight\Highlighter;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class SyntaxHighlighterTest extends TestCase
{
    #[Test]
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(SyntaxHighlighter::class));
    }

    #[Test]
    public function test_has_static_methods(): void
    {
        $this->assertTrue(method_exists(SyntaxHighlighter::class, 'highlight'));
        $this->assertTrue(method_exists(SyntaxHighlighter::class, 'getLanguageClass'));
    }

    #[Test]
    public function test_has_protected_static_highlighter_property(): void
    {
        $reflection = new ReflectionClass(SyntaxHighlighter::class);
        $this->assertTrue($reflection->hasProperty('highlighter'));

        $property = $reflection->getProperty('highlighter');
        $this->assertTrue($property->isProtected());
        $this->assertTrue($property->isStatic());
    }

    #[Test]
    public function test_highlight_method_signature(): void
    {
        $reflection = new ReflectionMethod(SyntaxHighlighter::class, 'highlight');

        $this->assertEquals(2, $reflection->getNumberOfParameters());
        $this->assertTrue($reflection->isPublic());
        $this->assertTrue($reflection->isStatic());

        $parameters = $reflection->getParameters();

        $this->assertEquals('code', $parameters[0]->getName());
        $this->assertFalse($parameters[0]->isDefaultValueAvailable());

        $this->assertEquals('language', $parameters[1]->getName());
        $this->assertTrue($parameters[1]->isDefaultValueAvailable());
        $this->assertEquals('auto', $parameters[1]->getDefaultValue());
    }

    #[Test]
    public function test_get_language_class_method_signature(): void
    {
        $reflection = new ReflectionMethod(SyntaxHighlighter::class, 'getLanguageClass');

        $this->assertEquals(1, $reflection->getNumberOfParameters());
        $this->assertTrue($reflection->isPublic());
        $this->assertTrue($reflection->isStatic());

        $parameter = $reflection->getParameters()[0];
        $this->assertEquals('language', $parameter->getName());
    }

    #[Test]
    public function test_highlight_initializes_highlighter_once(): void
    {
        $reflection = new ReflectionClass(SyntaxHighlighter::class);
        $highlighterProperty = $reflection->getProperty('highlighter');
        $highlighterProperty->setAccessible(true);

        // Reset the highlighter
        $highlighterProperty->setValue(null, null);

        // First call should initialize
        SyntaxHighlighter::highlight('<?php echo "test";', 'php');
        $firstInstance = $highlighterProperty->getValue();

        $this->assertInstanceOf(Highlighter::class, $firstInstance);

        // Second call should reuse the same instance
        SyntaxHighlighter::highlight('console.log("test");', 'javascript');
        $secondInstance = $highlighterProperty->getValue();

        $this->assertSame($firstInstance, $secondInstance);
    }

    #[Test]
    public function test_highlight_with_specific_language(): void
    {
        $code = '<?php echo "Hello World"; ?>';
        $result = SyntaxHighlighter::highlight($code, 'php');

        $this->assertIsString($result);
        // Should contain some highlighting markup or at least the code
        $this->assertStringContainsString('Hello World', $result);
    }

    #[Test]
    public function test_highlight_with_auto_detection(): void
    {
        $code = 'function test() { return "Hello"; }';
        $result = SyntaxHighlighter::highlight($code);

        $this->assertIsString($result);
        $this->assertStringContainsString('Hello', $result);
    }

    #[Test]
    public function test_highlight_handles_exceptions(): void
    {
        $reflection = new ReflectionClass(SyntaxHighlighter::class);
        $method = $reflection->getMethod('highlight');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Verify exception handling
        $this->assertStringContainsString('try {', $source);
        $this->assertStringContainsString('} catch (\Exception $e)', $source);
        $this->assertStringContainsString('htmlspecialchars($code)', $source);
    }

    #[Test]
    public function test_highlight_escapes_html_on_error(): void
    {
        // Test with potentially problematic code
        $code = '<script>alert("XSS")</script>';

        // Force an error by using invalid language
        $result = SyntaxHighlighter::highlight($code, 'invalid-language-that-does-not-exist');

        // Should escape HTML
        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringContainsString('&lt;/script&gt;', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    #[Test]
    public function test_get_language_class_returns_correct_classes(): void
    {
        $mappings = [
            'javascript' => 'language-javascript',
            'js'         => 'language-javascript',
            'python'     => 'language-python',
            'php'        => 'language-php',
            'bash'       => 'language-bash',
            'shell'      => 'language-bash',
            'json'       => 'language-json',
            'html'       => 'language-html',
            'css'        => 'language-css',
            'sql'        => 'language-sql',
            'yaml'       => 'language-yaml',
            'yml'        => 'language-yaml',
        ];

        foreach ($mappings as $input => $expected) {
            $result = SyntaxHighlighter::getLanguageClass($input);
            $this->assertEquals($expected, $result);
        }
    }

    #[Test]
    public function test_get_language_class_handles_case_insensitive(): void
    {
        $this->assertEquals('language-javascript', SyntaxHighlighter::getLanguageClass('JavaScript'));
        $this->assertEquals('language-python', SyntaxHighlighter::getLanguageClass('PYTHON'));
        $this->assertEquals('language-php', SyntaxHighlighter::getLanguageClass('PhP'));
    }

    #[Test]
    public function test_get_language_class_returns_default_for_unknown(): void
    {
        $this->assertEquals('language-plaintext', SyntaxHighlighter::getLanguageClass('unknown'));
        $this->assertEquals('language-plaintext', SyntaxHighlighter::getLanguageClass('rust'));
        $this->assertEquals('language-plaintext', SyntaxHighlighter::getLanguageClass('go'));
        $this->assertEquals('language-plaintext', SyntaxHighlighter::getLanguageClass(''));
    }

    #[Test]
    public function test_highlight_uses_highlighter_library(): void
    {
        $reflection = new ReflectionClass(SyntaxHighlighter::class);

        $fileName = $reflection->getFileName();
        $fileContent = file_get_contents($fileName);

        // Check imports
        $this->assertStringContainsString('use Highlight\Highlighter;', $fileContent);

        // Check usage
        $this->assertStringContainsString('new Highlighter()', $fileContent);
        $this->assertStringContainsString('highlightAuto($code)', $fileContent);
        $this->assertStringContainsString('highlight($language, $code)', $fileContent);
        $this->assertStringContainsString('->value', $fileContent);
    }

    #[Test]
    public function test_language_map_is_comprehensive(): void
    {
        $reflection = new ReflectionClass(SyntaxHighlighter::class);
        $method = $reflection->getMethod('getLanguageClass');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Check that common languages are supported
        $commonLanguages = ['javascript', 'python', 'php', 'bash', 'json', 'html', 'css', 'sql'];
        foreach ($commonLanguages as $lang) {
            $this->assertStringContainsString("'$lang'", $source);
        }

        // Check aliases
        $this->assertStringContainsString("'js'", $source);
        $this->assertStringContainsString("'shell'", $source);
        $this->assertStringContainsString("'yml'", $source);
    }

    protected function tearDown(): void
    {
        // Reset the static highlighter property
        $reflection = new ReflectionClass(SyntaxHighlighter::class);
        $highlighterProperty = $reflection->getProperty('highlighter');
        $highlighterProperty->setAccessible(true);
        $highlighterProperty->setValue(null, null);

        parent::tearDown();
    }
}
