<?php

namespace Vorkfork\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vorkfork\Core\Models\Job;

#[AsCommand(name: 'app:jobs', description: 'Execute jobs one by one')]
class JobsCommand extends Command
{
	protected function configure()
	{
		parent::configure(); // TODO: Change the autogenerated stub
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		while (true) {
			$job = $this->getLastOne(); // Get new job
			if (!is_null($job)) {
				//$job->setStatus(\Vorkfork\Core\Jobs\Job::STATUS_RUNNING); // Update status to running
				//$job->save(); // save status to DB
				$output->writeln('START ' . $job->getClass() . ' job');
				\Vorkfork\Core\Jobs\Job::execute($job);
				if ($job->getStatus() === \Vorkfork\Core\Jobs\Job::STATUS_FAILED) {
					$output->writeln('ERROR ' . $job->getClass() . ' job');
				} elseif ($job->getStatus() === \Vorkfork\Core\Jobs\Job::STATUS_FINISHED) {
					$output->writeln('SUCCESS ' . $job->getClass() . ' job');
				}
			}
			sleep(1);
		}
		return Command::SUCCESS;
	}

	public function getLastOne()
	{
		return Job::repository()->getLastNewJob();
	}
}