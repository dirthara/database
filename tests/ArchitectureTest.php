<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests;

use SplFileInfo;
use FilesystemIterator;
use RecursiveIteratorIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use PHPUnit\Framework\Attributes\Test;

use function sort;
use function sprintf;
use function in_array;
use function preg_match;
use function str_replace;
use function file_get_contents;

final class ArchitectureTest extends TestCase
{
    /**
     * Every PHP file under a directory, as a path relative to the repository root.
     *
     * @return list<string>
     */
    private function sources(string $directory): array
    {
        $root = __DIR__ . '/../';

        /** @var iterable<string, SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $root . $directory,
            FilesystemIterator::SKIP_DOTS,
        ));

        $paths = [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $paths[] = str_replace($root, replace: '', subject: $file->getPathname());
        }

        sort($paths);

        return $paths;
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(__DIR__ . '/../' . $path);
    }

    #[Test]
    public function the_query_objects_are_marked_internal(): void
    {
        $public = ['src/Query/Queries/CompiledQuery.php'];
        $unmarked = [];

        foreach (['src/Query/Clause', 'src/Query/Queries'] as $directory) {
            foreach ($this->sources($directory) as $path) {
                if (in_array($path, $public, strict: true)) {
                    continue;
                }

                if (preg_match('/@internal/', $this->read($path)) !== 1) {
                    $unmarked[] = $path;
                }
            }
        }

        self::assertSame(
            [],
            $unmarked,
            sprintf(
                'How a query is built is not public API, so every clause and query object needs an @internal tag.'
                . ' Add one, or list the file as public in %s.',
                self::class,
            ),
        );
    }
}
