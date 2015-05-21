<?php

/*
 * This file is part of the PHP Bench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\ReportGenerator;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use PhpBench\Result\SuiteResult;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class ConsoleTableReportGenerator extends BaseTabularReportGenerator
{
    public function doGenerate(SuiteResult $suite, OutputInterface $output, array $options)
    {
        $output = $output;
        $output->getFormatter()->setStyle(
            'total', new OutputFormatterStyle(null, null, array())
        );
        $output->getFormatter()->setStyle(
            'blue', new OutputFormatterStyle('blue', null, array())
        );
        $output->getFormatter()->setStyle(
            'dark', new OutputFormatterStyle('black', null, array('bold'))
        );

        foreach ($suite->getBenchmarkResults() as $benchmark) {
            foreach ($benchmark->getSubjectResults() as $subject) {
                $output->writeln(sprintf(
                    '<comment>%s#%s()</comment>: %s',
                    $benchmark->getClass(),
                    $subject->getName(),
                    $subject->getDescription()
                ));

                $data = $this->prepareData($subject, $options);
                $this->renderData($output, $data, $options);

                $output->writeln('');
            }
        }
    }

    private function renderData(OutputInterface $output, $data, $options)
    {
        // assign prevision to locally scoped variable for use in closures
        // in versions of PHP < 5.4
        $precision = $this->precision;

        $data->map(function ($cell) {
            return number_format($cell->value(), 2);
        }, array('revs'));


        switch ($options['time_format']) {
            case 'integer':
                // format the float cells
                $data->map(function ($cell) {
                    $value = $cell->value();
                    $value =  number_format($value);

                    return $value . '<dark>μs</dark>';
                }, array('time'));
                break;
            default:
                // format the float cells
                $data->map(function ($cell) use ($precision) {
                    $value = $cell->value();
                    $value =  number_format($value / 1000000, $precision);
                    $value = preg_replace('{^([0|\\.]+)(.+)$}', '<blue>\1</blue>\2', $value);

                    return $value . '<dark>s</dark>';
                }, array('time'));
        }

        // format the memory
        $data->map(function ($cell) {
            $value = $cell->value();

            return number_format($cell->value()) . '<dark>b</dark>';
        }, array('memory'));

        // format the revolutions
        $data->map(function ($cell) {
            return $cell->value() . '<dark>rps</dark>';
        }, array('revs'));

        // format the footer
        $data->map(function ($cell) {
            return sprintf('<total>%s</total>', $cell->value());
        }, array('footer'));

        $table = new Table($output);

        $table->setHeaders($data->getColumnNames());
        foreach ($data->getRows(array('spacer')) as $spacer) {
            $spacer->fill('--');
        }

        foreach ($data->getRows() as $row) {
            $row->map(function ($cell) {
                $value = $cell->value();

                if (is_scalar($value)) {
                    return $value;
                }

                if (is_object($value)) {
                    if (method_exists($value, '__toString')) {
                        return $value->__toString();
                    }

                    return 'obj:' . spl_object_hash($value);
                }

                return json_encode($value);
            });
            $table->addRow($row->toArray());
        }

        $table->render();
    }
}
