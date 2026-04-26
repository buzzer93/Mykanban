<?php

namespace App\Controller;

use App\Entity\Column;
use App\Entity\Task;
use App\Form\TaskType;
use App\Repository\ColumnRepository;
use App\Repository\TagRepository;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
final class TaskController extends AbstractController
{
    private const ALLOWED_SORTS = ['manual', 'smart', 'urgency', 'importance', 'deadline'];

    #[Route('/board', name: 'app_board', methods: ['GET'])]
    public function board(
        Request $request,
        ColumnRepository $columnRepository,
        TagRepository $tagRepository,
        TaskRepository $taskRepository,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        $sort = (string) $request->query->get('sort', 'manual');
        if (!in_array($sort, self::ALLOWED_SORTS, true)) {
            $sort = 'manual';
        }

        $selectedTagIds = array_values(array_filter(array_map('intval', (array) $request->query->all('tags'))));
        $columns = $columnRepository->findOrdered();

        $kanbanColumns = [];
        foreach ($columns as $column) {
            $kanbanColumns[] = [
                'column' => $column,
                'tasks' => $taskRepository->findBoardTasksForColumn($column, $selectedTagIds, $sort),
            ];
        }

        return $this->render('board/index.html.twig', [
            'kanbanColumns' => $kanbanColumns,
            'tags' => $tagRepository->findOrdered(),
            'selectedTagIds' => $selectedTagIds,
            'sort' => $sort,
            'sortChoices' => self::ALLOWED_SORTS,
            'moveTasksUrl' => $this->generateUrl('api_tasks_move'),
            'moveTasksToken' => $csrfTokenManager->getToken('move_tasks')->getValue(),
        ]);
    }

    #[Route('/api/tasks/move', name: 'api_tasks_move', methods: ['POST'])]
    public function move(Request $request, TaskRepository $taskRepository, ColumnRepository $columnRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Payload JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $token = isset($data['_token']) ? (string) $data['_token'] : '';
        if (!$this->isCsrfTokenValid('move_tasks', $token)) {
            return $this->json(['error' => 'CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $taskId = (int) ($data['taskId'] ?? 0);
        $targetColumnId = (int) ($data['targetColumnId'] ?? 0);
        $newPosition = (int) ($data['newPosition'] ?? -1);

        if ($taskId <= 0 || $targetColumnId <= 0 || $newPosition < 0) {
            return $this->json(['error' => 'Paramètres taskId/targetColumnId/newPosition invalides.'], Response::HTTP_BAD_REQUEST);
        }

        $task = $taskRepository->find($taskId);
        $targetColumn = $columnRepository->find($targetColumnId);

        if (!$task instanceof Task || !$targetColumn instanceof Column) {
            return $this->json(['error' => 'Tâche ou colonne cible introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if (null !== $task->getArchivedAt()) {
            return $this->json(['error' => 'Les tâches archivées ne peuvent pas être déplacées.'], Response::HTTP_BAD_REQUEST);
        }

        $sourceColumn = $task->getColumn();
        $sourceWasDone = $sourceColumn->isDone();
        $targetIsDone = $targetColumn->isDone();

        $sourceTasks = $taskRepository->findBy([
            'column' => $sourceColumn,
            'archivedAt' => null,
        ], ['position' => 'ASC', 'id' => 'ASC']);

        $sourceTasks = array_values(array_filter($sourceTasks, static fn (Task $candidate): bool => $candidate->getId() !== $task->getId()));

        if ($sourceColumn->getId() === $targetColumn->getId()) {
            $safePosition = max(0, min($newPosition, count($sourceTasks)));
            array_splice($sourceTasks, $safePosition, 0, [$task]);

            foreach ($sourceTasks as $position => $candidate) {
                $candidate->setPosition($position);
            }

            $entityManager->flush();

            return $this->json(['success' => true]);
        }

        $targetTasks = $taskRepository->findBy([
            'column' => $targetColumn,
            'archivedAt' => null,
        ], ['position' => 'ASC', 'id' => 'ASC']);

        $safePosition = max(0, min($newPosition, count($targetTasks)));
        array_splice($targetTasks, $safePosition, 0, [$task]);

        $task->setColumn($targetColumn);

        foreach ($sourceTasks as $position => $candidate) {
            $candidate->setPosition($position);
        }

        foreach ($targetTasks as $position => $candidate) {
            $candidate->setPosition($position);
        }

        if (!$sourceWasDone && $targetIsDone) {
            $task->setDoneAt(new \DateTimeImmutable());
        }

        if ($sourceWasDone && !$targetIsDone) {
            $task->setDoneAt(null);
        }

        $entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/tasks/new', name: 'tasks_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ColumnRepository $columnRepository,
        TagRepository $tagRepository,
        TaskRepository $taskRepository,
    ): Response {
        $tags = $tagRepository->findOrdered();
        if ([] === $tags) {
            $this->addFlash('error', 'Tu dois créer au moins un tag avant de créer une tâche.');

            return $this->redirectToRoute('settings_tags_index');
        }

        $firstColumn = $columnRepository->findFirstByPosition();
        if (null === $firstColumn) {
            $this->addFlash('error', 'Aucune colonne disponible. Crée d\'abord une colonne dans les settings.');

            return $this->redirectToRoute('settings_columns_index');
        }

        $task = new Task();
        $task->setColumn($firstColumn);
        $task->setPosition($taskRepository->getNextPositionInColumn($firstColumn));

        $form = $this->createForm(TaskType::class, $task, [
            'creation_mode' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $deadlineMode = (string) $form->get('deadlineMode')->getData();

            if ('date' === $deadlineMode) {
                if (null === $task->getDeadlineAt()) {
                    $form->get('deadlineAt')->addError(new FormError('Choisis une date/heure pour la deadline.'));
                }
            } else {
                $deadlineAmountRaw = $form->get('deadlineAmount')->getData();
                if (null === $deadlineAmountRaw || '' === $deadlineAmountRaw) {
                    $form->get('deadlineAmount')->addError(new FormError('Choisis une duree entre 1 et 9.'));
                }

                $deadlineAmount = max(1, min(9, (int) $deadlineAmountRaw));
                $deadlineUnit = (string) $form->get('deadlineUnit')->getData();
                if (!in_array($deadlineUnit, ['day', 'month'], true)) {
                    $form->get('deadlineUnit')->addError(new FormError('Choisis une unite valide.'));
                }

                if (0 === count($form->get('deadlineAmount')->getErrors(true)) && 0 === count($form->get('deadlineUnit')->getErrors(true))) {
                    $task->setDeadlineAt((new \DateTimeImmutable())->modify(sprintf('+%d %s', $deadlineAmount, $deadlineUnit)));
                }
            }

            if (!$form->isValid()) {
                return $this->render('tasks/new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            $entityManager->persist($task);
            $entityManager->flush();

            $this->addFlash('success', 'Tâche créée.');

            return $this->redirectToRoute('app_board');
        }

        return $this->render('tasks/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/tasks/{id}/edit', name: 'tasks_edit', methods: ['GET', 'POST'])]
    public function edit(Task $task, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Tâche mise à jour.');

            return $this->redirectToRoute('app_board');
        }

        return $this->render('tasks/edit.html.twig', [
            'task' => $task,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/tasks/{id}/delete', name: 'tasks_delete', methods: ['POST'])]
    public function delete(Task $task, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Token CSRF invalide pour la suppression de tâche.');

            return $this->redirect($this->resolveBoardRedirectUrl($request));
        }

        $entityManager->remove($task);
        $entityManager->flush();

        $this->addFlash('success', 'Tâche supprimée.');

        return $this->redirect($this->resolveBoardRedirectUrl($request));
    }

    private function resolveBoardRedirectUrl(Request $request): string
    {
        $referer = (string) $request->headers->get('referer', '');
        if ('' === $referer) {
            return $this->generateUrl('app_board');
        }

        $path = (string) parse_url($referer, PHP_URL_PATH);
        if ('/board' !== $path) {
            return $this->generateUrl('app_board');
        }

        $query = (string) parse_url($referer, PHP_URL_QUERY);

        return '' !== $query ? sprintf('%s?%s', $path, $query) : $path;
    }
}
