<?php

declare(strict_types=1);

namespace Happycode\TenZeroAuth\Command;

use Happycode\TenZeroAuth\Model\TenZeroUser;
use Happycode\TenZeroAuth\Service\ConfigService;
use Happycode\TenZeroAuth\Service\UserService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'tz:auth:adduser',
    description: 'Create a new TenZero auth user in the database',
)]
class TzAuthAdduserCommand extends Command
{
    public function __construct(
        private readonly UserService $userService,
        private readonly ConfigService $configService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'The user identifier (maps to the configured user_field, e.g. email)')
            ->addArgument('password', InputArgument::REQUIRED, 'The raw password for the new user')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = $input->getArgument('username');
        $password = $input->getArgument('password');

        $existingUser = $this->userService->fetchUser($username);
        if ($existingUser instanceof TenZeroUser) {
            $io->error(sprintf('A user with this %s already exists: %s', $this->configService->getUserField(), $username));

            return Command::FAILURE;
        }

        $userClass = $this->configService->getUserClass();
        $userField = $this->configService->getUserField();

        if (!is_subclass_of($userClass, TenZeroUser::class)) {
            $io->error(sprintf('Configured user_class "%s" must extend TenZeroUser.', $userClass));

            return Command::FAILURE;
        }

        $user = new $userClass();

        $setter = 'set'.ucfirst($userField);
        if (method_exists($user, $setter)) {
            $user->{$setter}($username);
        } else {
            $io->error(sprintf('Cannot set user_field "%s" on %s - no setter found.', $userField, $userClass));

            return Command::FAILURE;
        }

        foreach ($this->configService->getRegisterFields() as $field) {
            if (!is_string($field)) {
                continue;
            }
            $fieldSetter = 'set'.ucfirst($field);
            if (method_exists($user, $fieldSetter)) {
                $user->{$fieldSetter}('');
            }
        }

        $this->userService->setUserPassword($user, $password);

        $io->success(sprintf('User created successfully: %s', $username));

        return Command::SUCCESS;
    }
}
