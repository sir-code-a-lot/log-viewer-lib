<?php
namespace Syonix\LogViewer\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Syonix\LogViewer\Config;

class LintCommand extends Command
{
    private $configDefaultPath;
    
    protected function configure($configDefaultPath = null)
    {
        $this->configDefaultPath = $configDefaultPath;
        
        $this
            ->setName('config:lint')
            ->setDescription('Lint your config file to detect potential problems')
            ->addArgument(
                'config_file',
                ($this->configDefaultPath === null ? InputArgument::REQUIRED : InputArgument::OPTIONAL),
                'The path to your config file'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = ($input->getArgument('config_file') ? $input->getArgument('config_file') : $this->configDefaultPath);
        $output->writeln('Linting <info>'.basename($path).'</info>...');
        $output->writeln('');
        if(!is_file($path)) {
            throw new \InvalidArgumentException('"'.$path.'" is not a file.');
        } else if(!is_readable($path)) {
            throw new \InvalidArgumentException('"'.$path.'" can not be read.');
        }

        $lint = Config::lint(file_get_contents($path));

        $checkLines = array();
        foreach($lint['checks'] as $check) {
            $checkLines = $this->prepareCheckLine($checkLines, $check);
        }

        $table = new Table($output);
        $table->setStyle('compact');
        $table->setRows($checkLines);
        $table->render();

        $output->writeln('');
        if($lint['valid']) {
            $output->writeln('<fg=green>Your config file is valid.</>');
        } else {
            $output->writeln('<error> Your config file is not valid. </error>');
        }
    }

    private function prepareCheckLine($checkLines, $check, $level = 0)
    {
        $indentation = " ".str_repeat('   ', $level);

        $message = preg_replace('/"(.+)"/', '<fg=blue>${1}</>', $check['message']);
        $line[] = $indentation."> ".$message;
        switch($check['status']) {
            case 'ok':
                $line[] .= '  [ <fg=green>ok</> ]';
                break;
            case 'warn':
                $line[] .= '  [<fg=yellow>warn</>]';
                break;
            default:
                $line[] .= '  [<fg=red>fail</>]';
                break;
        }
        $checkLines[] = $line;

        if($check['error'] != '') {
            $prefix = $check['status'] == 'warn' ? '<fg=yellow>Warning:</>' : '<fg=red>Error:</>';
            $checkLines[][] = new TableCell($indentation.'  '.$prefix.' '.$check['error'], array('colspan' => 2));
        }

        if(!empty($check['sub_checks'])) {
            foreach($check['sub_checks'] as $subCheck) {
                $checkLines = $this->prepareCheckLine($checkLines, $subCheck, $level + 1);
            }
        }
        return $checkLines;
    }
}