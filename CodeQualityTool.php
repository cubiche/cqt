<?php

/**
 * This file is part of the Jadddp/code-quality-tools project.
 */
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Console\Application;
use Composer\Script\Event;

/**
 * CodeQualityTool.
 *
 * @author Karel Osorio Ramírez <osorioramirez@gmail.com>
 */
class CodeQualityTool extends Application
{
    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var InputInterface
     */
    protected $input;

    const PHP_FILES_IN_SRC = '/^src\/(.*)(\.php)$/';

    public function __construct()
    {
        parent::__construct('Jadddp Code Quality Tool', '1.0.0');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Console\Application::doRun()
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $output->writeln(
            \sprintf(
                '<fg=white;options=bold;bg=blue>%s %s</fg=white;options=bold;bg=blue>',
                $this->getName(),
                $this->getVersion()
            )
        );

        $output->writeln('<info>Fetching files</info>');
        $files = $this->extractCommitedFiles();

        $output->writeln('<info>Check composer</info>');
        $this->checkComposer($files);

        $output->writeln('<info>Running PHPLint</info>');
        if (!$this->phpLint($files)) {
            throw new \Exception('There are some PHP syntax errors!');
        }

        $output->writeln('<info>Checking code style</info>');
        if (!$this->codeStyle($files)) {
            throw new \Exception(sprintf('There are coding standards violations!'));
        }

        $output->writeln('<info>Checking code style with PHPCS</info>');
        if (!$this->codeStylePsr($files)) {
            throw new \Exception(sprintf('There are PHPCS coding standards violations!'));
        }

        $output->writeln('<info>Checking code mess with PHPMD</info>');
        if (!$this->phPmd($files)) {
            throw new \Exception(sprintf('There are PHPMD violations!'));
        }

        $output->writeln('<info>Running unit tests</info>');
        if (!$this->unitTests()) {
            throw new \Exception('Fix the unit tests!');
        }

        $output->writeln('<info>Good job dude!</info>');
    }

    /**
     * @param array $files
     */
    private function checkComposer(array $files)
    {
        $composerJsonDetected = false;
        $composerLockDetected = false;
        foreach ($files as $file) {
            if ($file === 'composer.json') {
                $composerJsonDetected = true;
            }
            if ($file === 'composer.lock') {
                $composerLockDetected = true;
            }
        }
        if ($composerJsonDetected && !$composerLockDetected) {
            $this->output->writeln(
                '
                    <bg=yellow;fg=black>
                    composer.lock must be commited if composer.json is modified!
                    </bg=yellow;fg=black>'
            );
        }
    }

    /**
     * @return array
     */
    protected function extractCommitedFiles()
    {
        $output = array();
        $rc = 0;
        exec('git rev-parse --verify HEAD 2> /dev/null', $output, $rc);
        $against = '4b825dc642cb6eb9a060e54bf8d69288fbee4904';
        if ($rc === 0) {
            $against = 'HEAD';
        }
        exec("git diff-index --cached --name-status $against | egrep '^(A|M)' | awk '{print $2;}'", $output);

        return $output;
    }

    /**
     * @param array $files
     *
     * @return bool
     */
    protected function phpLint(array $files)
    {
        $needle = '/(\.php)|(\.inc)$/';
        $succeed = true;
        foreach ($files as $file) {
            if (!preg_match($needle, $file)) {
                continue;
            }
            $processBuilder = new ProcessBuilder(array('php', '-l', $file));
            $process = $processBuilder->getProcess();
            $process->run();
            if (!$process->isSuccessful()) {
                $this->output->writeln($file);
                $this->output->writeln(sprintf('<error>%s</error>', trim($process->getErrorOutput())));
                if ($succeed) {
                    $succeed = false;
                }
            }
        }

        return $succeed;
    }

    /**
     * @param array $files
     *
     * @return bool
     */
    protected function phPmd(array $files)
    {
        $needle = self::PHP_FILES_IN_SRC;
        $succeed = true;
        $rootPath = realpath(getcwd());
        foreach ($files as $file) {
            if (!preg_match($needle, $file)) {
                continue;
            }
            $processBuilder = new ProcessBuilder(['php', 'bin/phpmd', $file, 'text', 'controversial']);
            $processBuilder->setWorkingDirectory($rootPath);
            $process = $processBuilder->getProcess();
            $process->run();
            if (!$process->isSuccessful()) {
                $this->output->writeln($file);
                $this->output->writeln(sprintf('<error>%s</error>', trim($process->getErrorOutput())));
                $this->output->writeln(sprintf('<info>%s</info>', trim($process->getOutput())));
                if ($succeed) {
                    $succeed = false;
                }
            }
        }

        return $succeed;
    }

    /**
     * @return bool
     */
    private function unitTests()
    {
        $processBuilder = new ProcessBuilder(array('php', 'bin/phpunit', '--colors'));
        $processBuilder->setWorkingDirectory(getcwd());
        $processBuilder->setTimeout(3600);
        $phpunit = $processBuilder->getProcess();
        $phpunit->run(
            function ($type, $buffer) {
                $this->output->write($buffer);
            }
        );

        return $phpunit->isSuccessful();
    }

    /**
     * @param array $files
     *
     * @return bool
     */
    protected function codeStyle(array $files)
    {
        $succeed = true;
        foreach ($files as $file) {
            $srcFile = preg_match(self::PHP_FILES_IN_SRC, $file);
            if (!$srcFile) {
                continue;
            }
            $fixers = '
                -psr0,eof_ending,indentation,linefeed,lowercase_keywords,trailing_spaces,
                short_tag,php_closing_tag,extra_empty_lines,elseif,function_declaration
            ';
            $processBuilder = new ProcessBuilder(
                array(
                'php', 'bin/php-cs-fixer', '--dry-run', '--verbose', 'fix', $file, '--fixers='.$fixers,
                )
            );
            $processBuilder->setWorkingDirectory(getcwd());
            $phpCsFixer = $processBuilder->getProcess();
            $phpCsFixer->run();
            if (!$phpCsFixer->isSuccessful()) {
                $this->output->writeln(sprintf('<error>%s</error>', trim($phpCsFixer->getOutput())));
                if ($succeed) {
                    $succeed = false;
                }
            }
        }

        return $succeed;
    }

    /**
     * @param array $files
     *
     * @return bool
     */
    private function codeStylePsr(array $files)
    {
        $succeed = true;
        $needle = self::PHP_FILES_IN_SRC;
        foreach ($files as $file) {
            if (!preg_match($needle, $file)) {
                continue;
            }
            $processBuilder = new ProcessBuilder(array('php', 'bin/phpcs', '--standard=PSR2', $file));
            $processBuilder->setWorkingDirectory(getcwd());
            $phpCsFixer = $processBuilder->getProcess();
            $phpCsFixer->run(
                function ($type, $buffer) {
                    $this->output->write($buffer);
                }
            );
            if (!$phpCsFixer->isSuccessful()) {
                $this->output->writeln(sprintf('<error>%s</error>', trim($phpCsFixer->getOutput())));
                if ($succeed) {
                    $succeed = false;
                }
            }
        }

        return $succeed;
    }

    /**
     * @param Event $event
     */
    public static function checkHooks(Event $event)
    {
        $io = $event->getIO();

        if (!is_dir(getcwd().'/.git/hooks')) {
            $io->write('<error>The .git/hooks directory does not exist, execute git init</error>');

            return;
        }

        $gitPath = getcwd().'/.git/hooks/pre-commit';
        $docPath = __DIR__.'/pre-commit';
        $gitHook = @file_get_contents($gitPath);
        $docHook = @file_get_contents($docPath);
        if ($gitHook !== $docHook) {
            self::createSymlink($gitPath, $docPath);
        }
    }

    /**
     * @param string $symlinkTarget
     * @param string $symlinkName
     *
     * @throws \Exception
     */
    private static function createSymlink($symlinkTarget, $symlinkName)
    {
        if (!@readlink($symlinkName)) {
            $processBuilder = new ProcessBuilder(array('rm', '-rf', $symlinkTarget));
            $process = $processBuilder->getProcess();
            $process->run();

            if (false === symlink($symlinkName, $symlinkTarget)) {
                throw new \Exception('Error occured when trying to create a symlink.');
            }
        }
    }
}
