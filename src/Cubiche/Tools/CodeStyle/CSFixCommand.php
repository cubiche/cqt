<?php

/**
 * This file is part of the Cubiche application.
 *
 * Copyright (c) Cubiche
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Cubiche\Tools\CodeStyle;

use Cubiche\Tools\ConfigUtils;
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

        parent::__construct($fixer, Config::create()->finder($finder)->fixers($this->fixers()));
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

    /**
     * @return array
     */
    protected function fixers()
    {
        $config = ConfigUtils::getConfig('phpcsfixer', array(
            'fixers' => [
                '-psr0','eof_ending','indentation','linefeed','lowercase_keywords','trailing_spaces', 'short_tag',
                'php_closing_tag','extra_empty_lines','elseif','function_declaration', '-phpdoc_scalar', '-phpdoc_types'
            ]
        ));

        return $config['fixers'];
    }
}
