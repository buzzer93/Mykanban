<?php

namespace App\DataFixtures;

use App\Entity\Column;
use App\Entity\Tag;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setEmail('admin@admin.com');
        $admin->setUsername('admin');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'adminadmin'));
        $manager->persist($admin);

        $todoColumn = (new Column())
            ->setName('A faire')
            ->setPosition(0)
            ->setIsDone(false);

        $doingColumn = (new Column())
            ->setName('En cours')
            ->setPosition(1)
            ->setIsDone(false);

        $doneColumn = (new Column())
            ->setName('Termine')
            ->setPosition(2)
            ->setIsDone(true);

        $manager->persist($todoColumn);
        $manager->persist($doingColumn);
        $manager->persist($doneColumn);

        $administratifTag = (new Tag())
            ->setName('Administratif')
            ->setColor('#0ea5e9')
            ->setPosition(0);

        $comptableTag = (new Tag())
            ->setName('Comptable')
            ->setColor('#22c55e')
            ->setPosition(1);

        $codeTag = (new Tag())
            ->setName('Code')
            ->setColor('#7132f5')
            ->setPosition(2);

        $itylonTag = (new Tag())
            ->setName('Itylon')
            ->setColor('#f59e0b')
            ->setPosition(3);

        $manager->persist($administratifTag);
        $manager->persist($comptableTag);
        $manager->persist($codeTag);
        $manager->persist($itylonTag);

        $now = new \DateTimeImmutable();

        $task1CreatedAt = $now->modify('-10 days');
        $task1DeadlineAt = $task1CreatedAt->modify('+1 month');

        $task1 = (new Task())
            ->setTitle('Preparer la declaration d\'impots')
            ->setDescription('Rassembler les justificatifs et verifier les pieces manquantes.')
            ->setImportance(4)
            ->setCreatedAt($task1CreatedAt)
            ->setDeadlineAt($task1DeadlineAt)
            ->setColumn($todoColumn)
            ->setPosition(0)
            ->addTag($administratifTag);

        $task2CreatedAt = $now->modify('-20 days');
        $task2DeadlineAt = $task2CreatedAt->modify('+1 month');

        $task2 = (new Task())
            ->setTitle('Faire le budget du mois')
            ->setDescription('Mettre a jour les charges fixes et ajuster le reste a vivre.')
            ->setImportance(5)
            ->setCreatedAt($task2CreatedAt)
            ->setDeadlineAt($task2DeadlineAt)
            ->setColumn($todoColumn)
            ->setPosition(1)
            ->addTag($comptableTag);

        $task3CreatedAt = $now->modify('-5 days');
        $task3DeadlineAt = $task3CreatedAt->modify('+9 days');

        $task3 = (new Task())
            ->setTitle('Corriger le bug de tri des taches')
            ->setDescription('Verifier le tri manuel et les modes smart/urgence/importances.')
            ->setImportance(3)
            ->setCreatedAt($task3CreatedAt)
            ->setDeadlineAt($task3DeadlineAt)
            ->setColumn($doingColumn)
            ->setPosition(0)
            ->addTag($codeTag);

        $task4CreatedAt = $now->modify('-12 days');
        $task4DeadlineAt = $task4CreatedAt->modify('+6 days');

        $task4 = (new Task())
            ->setTitle('Reparer la fuite sous l\'evier')
            ->setDescription('Acheter le joint et refaire l\'etancheite du siphon.')
            ->setImportance(4)
            ->setCreatedAt($task4CreatedAt)
            ->setDeadlineAt($task4DeadlineAt)
            ->setColumn($doneColumn)
            ->setPosition(0)
            ->setDoneAt($now->modify('-5 days'))
            ->addTag($itylonTag);

        $task5CreatedAt = $now->modify('-40 days');
        $task5DeadlineAt = $task5CreatedAt->modify('+1 month');

        $task5 = (new Task())
            ->setTitle('Acheter de la crypto (DCA mensuel)')
            ->setDescription('Executer l\'achat prevu et enregistrer l\'operation dans le suivi.')
            ->setImportance(3)
            ->setCreatedAt($task5CreatedAt)
            ->setDeadlineAt($task5DeadlineAt)
            ->setColumn($doneColumn)
            ->setPosition(1)
            ->setDoneAt($now->modify('-8 days'))
            ->addTag($comptableTag);

        $manager->persist($task1);
        $manager->persist($task2);
        $manager->persist($task3);
        $manager->persist($task4);
        $manager->persist($task5);

        $manager->flush();
    }
}
