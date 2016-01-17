<?php

/**
 * This file is part of the Cubiche/cqt project.
 */
namespace Cubiche\Tools\CodeStyle;

use Symfony\CS\Console\Command\FixCommand;
use Symfony\CS\Fixer;
use Symfony\CS\Finder\DefaultFinder;
use Symfony\CS\Config\Config;
use Cubiche\Tools\GitUtils;

/**
 * CSFixCommand.
 *
 * @author Karel Osorio Ramírez <osorioramirez@gmail.com>
 */
class CSFixCommand extends FixCommand
{
    /**
     * @param Fixer $fixer
     */
    public function __construct(Fixer $fixer = null)
    {
        $finder = DefaultFinder::create();
        $finder->append($this->commitedFiles());

        parent::__construct($fixer, Config::create()->finder($finder));
    }

    /**
     * @return Generator
     */
    protected function commitedFiles()
    {
        foreach (GitUtils::commitedFiles() as $file) {
            yield new \SplFileInfo($file);
        }
    }
}
