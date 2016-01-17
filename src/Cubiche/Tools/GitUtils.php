<?php

/**
 * This file is part of the Cubiche/cqt project.
 */
namespace Cubiche\Tools;

/**
 * GitUtils.
 *
 * @author Karel Osorio Ramírez <osorioramirez@gmail.com>
 * @author Ivannis Suárez Jérez <ivannis.suarez@gmail.com>
 */
class GitUtils
{
    const PHP_FILES = '/(\.php)$/';

    /**
     * @var array
     */
    private static $files = null;

    /**
     * @return array
     */
    public static function extractCommitedFiles()
    {
        if (self::$files === null) {
            self::$files = array();
            $rc = 0;
            exec('git rev-parse --verify HEAD 2> /dev/null', self::$files, $rc);
            $against = '4b825dc642cb6eb9a060e54bf8d69288fbee4904';
            if ($rc === 0) {
                $against = 'HEAD';
            }
            exec("git diff-index --cached --name-status $against | egrep '^(A|M)' | awk '{print $2;}'", self::$files);
        }

        return self::$files;
    }

    /**
     * @param string $pattern
     *
     * @return Generator
     */
    public static function commitedFiles($pattern = self::PHP_FILES)
    {
        foreach (self::extractCommitedFiles() as $file) {
            if (preg_match($pattern, $file) === 1) {
                yield $file;
            }
        }
    }
}
