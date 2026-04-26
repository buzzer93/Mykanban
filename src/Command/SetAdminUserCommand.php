<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

#[AsCommand(
    name: 'app:user:set-admin',
    description: 'Crée ou met à jour l\'utilisateur admin unique (identifiant + email + mot de passe).',
)]
class SetAdminUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
                        ->addArgument('username', InputArgument::OPTIONAL, 'Identifiant de l\'admin')
                        ->addArgument('email', InputArgument::OPTIONAL, 'Email de l\'admin')
                        ->addArgument('password', InputArgument::OPTIONAL, 'Mot de passe en clair (sera hashé)')
            ->setHelp(<<<'HELP'
                Met en place l'unique utilisateur admin de Mykanban.

                Exemples :
                                    <info>php %command.full_name% nicolas admin@example.com</info>        (demande interactive du mot de passe)
                                    <info>php %command.full_name% nicolas admin@example.com 'motdepasse'</info>
                                    <comment>Compat legacy :</comment> <info>php %command.full_name% admin@example.com</info>

                                Si un utilisateur avec cet identifiant existe déjà, son email/mot de passe
                                (et son rôle ROLE_ADMIN) sont mis à jour.
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $usernameArgument = $input->getArgument('username');
        $emailArgument = $input->getArgument('email');

        // Compatibilite: ancien usage avec un seul argument email.
        if (is_string($usernameArgument) && null === $emailArgument && str_contains($usernameArgument, '@')) {
            $emailArgument = $usernameArgument;
            $usernameArgument = null;
        }

        $username = $usernameArgument ?? $io->ask('Identifiant', null, $this->usernameValidator(...));
        $this->usernameValidator($username);

        $email = $emailArgument ?? $io->ask('Email', null, $this->emailValidator(...));
        $this->emailValidator($email);

        $password = $input->getArgument('password');
        if (null === $password) {
            $question = new Question('Mot de passe');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $question->setValidator($this->passwordValidator(...));
            $password = $io->askQuestion($question);
        } else {
            $this->passwordValidator($password);
        }

        $user = $this->users->findOneByUsername($username);
        if (null === $user) {
            $user = $this->users->findOneByEmail($email);
        }

        $created = false;
        if (null === $user) {
            $user = new User();
            $created = true;
        }

        $user->setUsername($username);
        $user->setEmail($email);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->hasher->hashPassword($user, $password));

        if ($created) {
            $this->em->persist($user);
        }
        $this->em->flush();

        $io->success(sprintf(
            '%s : %s (rôle ROLE_ADMIN).',
            $created ? 'Admin créé' : 'Admin mis à jour',
            sprintf('%s / %s', $username, $email),
        ));

        return Command::SUCCESS;
    }

    private function usernameValidator(?string $value): string
    {
        $validator = Validation::createValidator();
        $violations = $validator->validate($value, [new NotBlank(), new Length(min: 3, max: 50)]);
        if (0 !== count($violations)) {
            throw new \InvalidArgumentException((string) $violations[0]->getMessage());
        }

        return trim((string) $value);
    }

    private function emailValidator(?string $value): string
    {
        $validator = Validation::createValidator();
        $violations = $validator->validate($value, [new NotBlank(), new Email()]);
        if (0 !== count($violations)) {
            throw new \InvalidArgumentException((string) $violations[0]->getMessage());
        }

        return $value;
    }

    private function passwordValidator(?string $value): string
    {
        if (null === $value || '' === trim($value)) {
            throw new \InvalidArgumentException('Le mot de passe ne peut pas être vide.');
        }
        if (mb_strlen($value) < 8) {
            throw new \InvalidArgumentException('Le mot de passe doit faire au moins 8 caractères.');
        }

        return $value;
    }
}
