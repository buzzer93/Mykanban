<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Column;
use App\Entity\Tag;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class TaskMoveApiTest extends WebTestCase
{
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    private int $taskId;

    private int $todoColumnId;

    private int $doneColumnId;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->resetSchema();

        $user = (new User())
            ->setUsername('drag-test')
            ->setEmail('drag-test@example.com')
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword('hashed-password');

        $todoColumn = (new Column())
            ->setName('A faire')
            ->setPosition(0)
            ->setIsDone(false);

        $doneColumn = (new Column())
            ->setName('Termine')
            ->setPosition(1)
            ->setIsDone(true);

        $tag = (new Tag())
            ->setName('Infra')
            ->setColor('#0ea5e9')
            ->setPosition(0);

        $task = (new Task())
            ->setTitle('Verifier move API')
            ->setDescription('Test fonctionnel drag and drop')
            ->setColumn($todoColumn)
            ->setPosition(0)
            ->addTag($tag);

        $this->entityManager->persist($user);
        $this->entityManager->persist($todoColumn);
        $this->entityManager->persist($doneColumn);
        $this->entityManager->persist($tag);
        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $this->taskId = (int) $task->getId();
        $this->todoColumnId = (int) $todoColumn->getId();
        $this->doneColumnId = (int) $doneColumn->getId();

        $this->client->loginUser($user);
    }

    public function testMoveTaskToDoneColumnWithValidCsrfToken(): void
    {
        /** @var User $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'drag-test@example.com']);
        $this->client->loginUser($user);

        $csrfToken = static::getContainer()
            ->get(CsrfTokenManagerInterface::class)
            ->getToken('move_tasks')
            ->getValue();

        $this->client->request(
            'POST',
            '/api/tasks/move',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
                'HTTP_ORIGIN' => 'http://localhost',
            ],
            content: json_encode([
                'taskId' => $this->taskId,
                'targetColumnId' => $this->doneColumnId,
                'newPosition' => 0,
                '_token' => $csrfToken,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();

        $responsePayload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue((bool) ($responsePayload['success'] ?? false));

        $this->entityManager->clear();

        /** @var TaskRepository $taskRepository */
        $taskRepository = $this->entityManager->getRepository(Task::class);
        $task = $taskRepository->find($this->taskId);

        self::assertInstanceOf(Task::class, $task);
        self::assertSame($this->doneColumnId, $task->getColumn()?->getId());
        self::assertNotNull($task->getDoneAt());
    }

    public function testMoveTaskReturnsForbiddenWithInvalidCsrfToken(): void
    {
        /** @var User $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'drag-test@example.com']);
        $this->client->loginUser($user);

        $this->client->request(
            'POST',
            '/api/tasks/move',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
                'HTTP_ORIGIN' => 'http://localhost',
            ],
            content: json_encode([
                'taskId' => $this->taskId,
                'targetColumnId' => $this->doneColumnId,
                'newPosition' => 0,
                '_token' => 'invalid-csrf-token',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(403);

        $responsePayload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('CSRF invalide.', $responsePayload['error'] ?? null);

        $this->entityManager->clear();

        /** @var TaskRepository $taskRepository */
        $taskRepository = $this->entityManager->getRepository(Task::class);
        $task = $taskRepository->find($this->taskId);

        self::assertInstanceOf(Task::class, $task);
        self::assertSame($this->todoColumnId, $task->getColumn()?->getId());
        self::assertNull($task->getDoneAt());
    }

    private function resetSchema(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }
}
