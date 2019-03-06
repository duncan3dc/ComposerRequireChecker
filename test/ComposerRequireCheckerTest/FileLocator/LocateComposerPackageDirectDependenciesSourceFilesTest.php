<?php

namespace ComposerRequireCheckerTest\FileLocator;

use ComposerRequireChecker\FileLocator\LocateComposerPackageDirectDependenciesSourceFiles;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ComposerRequireChecker\FileLocator\LocateComposerPackageDirectDependenciesSourceFiles
 */
class LocateComposerPackageDirectDependenciesSourceFilesTest extends TestCase
{
    /** @var LocateComposerPackageDirectDependenciesSourceFiles */
    private $locator;
    /** @var vfsStreamDirectory */
    private $root;

    protected function setUp()
    {
        parent::setUp();

        $this->locator = new LocateComposerPackageDirectDependenciesSourceFiles();
        $this->root = vfsStream::setup();
    }

    public function testNoDependencies()
    {
        $composerJson = vfsStream::newFile('composer.json')->at($this->root)->withContent('{}')->url();

        $files = $this->locate($composerJson);

        $this->assertCount(0, $files);
    }

    public function testSingleDependency()
    {
        vfsStream::create([
            'composer.json' => '{"require":{"foo/bar": "^1.0"}}',
            'vendor' => [
                'composer' => [
                    'installed.json' => '{"packages":[{"name": "foo/bar", "autoload":{"psr-4":{"":"src"}}}]}',
                ],
                'foo' => [
                    'bar' => [
                        'src' => [
                            'MyClass.php' => '',
                        ],
                    ],
                ],
            ],
        ]);

        $files = $this->locate($this->root->getChild('composer.json')->url());

        $this->assertCount(1, $files);

        $expectedFile = $this->root->getChild('vendor/foo/bar/src/MyClass.php')->url();
        $actualFile = str_replace('\\', '/', reset($files));
        $this->assertSame($expectedFile, $actualFile);
    }

    public function testVendorDirsWithoutComposerFilesAreIgnored()
    {
        vfsStream::create([
            'composer.json' => '{"require": {"foo/bar": "^1.0"}}',
            'vendor' => [
                'composer' => [
                    'installed.json' => '{"packages":[]}',
                ],
                'foo' => [
                    'bar' => [
                        'src' => [
                            'MyClass.php' => '',
                        ],
                    ],
                ],
            ],
        ]);

        $files = $this->locate($this->root->getChild('composer.json')->url());

        $this->assertCount(0, $files);
    }

    public function testVendorConfigSettingIsBeingUsed()
    {
        vfsStream::create([
            'composer.json' => '{"require":{"foo/bar": "^1.0"},"config":{"vendor-dir":"alternate-vendor"}}',
            'alternate-vendor' => [
                'composer' => [
                    'installed.json' => '{"packages":[{"name": "foo/bar", "autoload":{"psr-4":{"":"src"}}}]}',
                ],
                'foo' => [
                    'bar' => [
                        'src' => [
                            'MyClass.php' => '',
                        ],
                    ],
                ],
            ],
        ]);

        $files = $this->locate($this->root->getChild('composer.json')->url());

        $this->assertCount(1, $files);

        $expectedFile = $this->root->getChild('alternate-vendor/foo/bar/src/MyClass.php')->url();
        $actualFile = str_replace('\\', '/', reset($files));
        $this->assertSame($expectedFile, $actualFile);
    }

    public function testInstalledJsonUsedAsFallback()
    {
        vfsStream::create([
            'composer.json' => '{"require":{"foo/bar": "^1.0"}}',
            'vendor' => [
                'composer' => [
                    'installed.json' => '{"packages": [{"name": "foo/bar", "autoload":{"psr-4":{"":"src"}}}]}',
                ],
                'foo' => [
                    'bar' => [
                        'src' => [
                            'MyClass.php' => '',
                        ],
                    ],
                ],
            ],
        ]);

        $files = $this->locate($this->root->getChild('composer.json')->url());

        $this->assertCount(1, $files);

        $expectedFile = $this->root->getChild('vendor/foo/bar/src/MyClass.php')->url();
        $actualFile = str_replace('\\', '/', reset($files));
        $this->assertSame($expectedFile, $actualFile);

        # Ensure we didn't leave our temporary composer.json lying around
        $this->assertFalse($this->root->hasChild('vendor/foo/bar/composer.json'));
    }


    /**
     * https://github.com/composer/composer/pull/7999
     */
    public function testOldInstalledJsonUsedAsFallback()
    {
        vfsStream::create([
            'composer.json' => '{"require":{"foo/bar": "^1.0"}}',
            'vendor' => [
                'composer' => [
                    'installed.json' => '[{"name": "foo/bar", "autoload":{"psr-4":{"":"src"}}}]',
                ],
                'foo' => [
                    'bar' => [
                        'src' => [
                            'MyClass.php' => '',
                        ],
                    ],
                ],
            ],
        ]);

        $files = $this->locate($this->root->getChild('composer.json')->url());

        $this->assertCount(1, $files);

        $expectedFile = $this->root->getChild('vendor/foo/bar/src/MyClass.php')->url();
        $actualFile = str_replace('\\', '/', reset($files));
        $this->assertSame($expectedFile, $actualFile);

        # Ensure we didn't leave our temporary composer.json lying around
        $this->assertFalse($this->root->hasChild('vendor/foo/bar/composer.json'));
    }

    /**
     * @return string[]
     */
    private function locate(string $composerJson): array
    {
        return iterator_to_array(($this->locator)($composerJson));
    }
}
