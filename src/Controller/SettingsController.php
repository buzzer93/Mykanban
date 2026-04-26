<?php

namespace App\Controller;

use App\Entity\Column;
use App\Entity\Tag;
use App\Form\ColumnType;
use App\Form\TagType;
use App\Repository\ColumnRepository;
use App\Repository\TagRepository;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
final class SettingsController extends AbstractController
{
    #[Route('/settings/columns', name: 'settings_columns_index', methods: ['GET'])]
    public function columnsIndex(ColumnRepository $columnRepository): Response
    {
        return $this->render('settings/columns/index.html.twig', [
            'columns' => $columnRepository->findBy([], ['position' => 'ASC', 'id' => 'ASC']),
        ]);
    }

    #[Route('/settings/columns/new', name: 'settings_columns_new', methods: ['GET', 'POST'])]
    public function columnsNew(Request $request, EntityManagerInterface $entityManager, ColumnRepository $columnRepository): Response
    {
        $column = new Column();
        $form = $this->createForm(ColumnType::class, $column);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$column->isDone() && 0 === $columnRepository->countDoneColumns()) {
                $this->addFlash('error', 'La première colonne doit être marquée Terminé (isDone).');

                return $this->redirectToRoute('settings_columns_new');
            }

            $column->setPosition($columnRepository->getNextPosition());

            $entityManager->persist($column);
            $entityManager->flush();

            $this->addFlash('success', 'Colonne créée.');

            return $this->redirectToRoute('settings_columns_index');
        }

        return $this->render('settings/columns/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/settings/columns/{id}/edit', name: 'settings_columns_edit', methods: ['GET', 'POST'])]
    public function columnsEdit(Column $column, Request $request, EntityManagerInterface $entityManager, ColumnRepository $columnRepository): Response
    {
        $previousIsDone = $column->isDone();
        $form = $this->createForm(ColumnType::class, $column);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($previousIsDone && !$column->isDone() && $columnRepository->countDoneColumns() <= 1) {
                $this->addFlash('error', 'Impossible de retirer isDone: au moins une colonne doit rester marquée Terminé.');

                return $this->redirectToRoute('settings_columns_edit', ['id' => $column->getId()]);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Colonne mise à jour.');

            return $this->redirectToRoute('settings_columns_index');
        }

        return $this->render('settings/columns/edit.html.twig', [
            'column' => $column,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/settings/columns/{id}/delete', name: 'settings_columns_delete', methods: ['POST'])]
    public function columnsDelete(
        Column $column,
        Request $request,
        EntityManagerInterface $entityManager,
        ColumnRepository $columnRepository,
        TaskRepository $taskRepository,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Token CSRF invalide pour la suppression de colonne.');

            return $this->redirectToRoute('settings_columns_index');
        }

        $allColumns = $columnRepository->findAll();
        if (count($allColumns) <= 1) {
            $this->addFlash('error', 'Impossible de supprimer la dernière colonne.');

            return $this->redirectToRoute('settings_columns_index');
        }

        if ($column->isDone() && $columnRepository->countDoneColumns() <= 1) {
            $this->addFlash('error', 'Impossible de supprimer la dernière colonne isDone.');

            return $this->redirectToRoute('settings_columns_index');
        }

        if ($column->getTasks()->count() > 0) {
            $targetColumnId = (int) $request->request->get('target_column_id');
            $targetColumn = $columnRepository->find($targetColumnId);

            if (null === $targetColumn || $targetColumn->getId() === $column->getId()) {
                $this->addFlash('error', 'Cette colonne contient des tâches: choisis une colonne cible valide pour les migrer.');

                return $this->redirectToRoute('settings_columns_index');
            }

            $nextPosition = $taskRepository->getNextPositionInColumn($targetColumn);
            foreach ($column->getTasks() as $task) {
                $task->setColumn($targetColumn);
                $task->setPosition($nextPosition);
                ++$nextPosition;
            }
        }

        $entityManager->remove($column);
        $entityManager->flush();

        $this->normalizeColumnPositions($columnRepository, $entityManager);
        $this->addFlash('success', 'Colonne supprimée.');

        return $this->redirectToRoute('settings_columns_index');
    }

    #[Route('/api/settings/columns/reorder', name: 'api_settings_columns_reorder', methods: ['POST'])]
    public function columnsReorder(Request $request, ColumnRepository $columnRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Payload JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $token = isset($data['_token']) ? (string) $data['_token'] : '';
        if (!$this->isCsrfTokenValid('reorder_columns', $token)) {
            return $this->json(['error' => 'CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $orderedIds = $data['orderedIds'] ?? null;
        if (!is_array($orderedIds)) {
            return $this->json(['error' => 'orderedIds doit être un tableau.'], Response::HTTP_BAD_REQUEST);
        }

        $columns = $columnRepository->findAll();
        if (count($orderedIds) !== count($columns)) {
            return $this->json(['error' => 'orderedIds doit contenir toutes les colonnes.'], Response::HTTP_BAD_REQUEST);
        }

        $columnsById = [];
        foreach ($columns as $column) {
            $columnsById[$column->getId()] = $column;
        }

        foreach ($orderedIds as $position => $id) {
            $id = (int) $id;
            if (!isset($columnsById[$id])) {
                return $this->json(['error' => 'ID de colonne inconnu dans orderedIds.'], Response::HTTP_BAD_REQUEST);
            }

            $columnsById[$id]->setPosition($position);
        }

        $entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/settings/tags', name: 'settings_tags_index', methods: ['GET'])]
    public function tagsIndex(TagRepository $tagRepository): Response
    {
        return $this->render('settings/tags/index.html.twig', [
            'tags' => $tagRepository->findBy([], ['position' => 'ASC', 'id' => 'ASC']),
        ]);
    }

    #[Route('/settings/tags/new', name: 'settings_tags_new', methods: ['GET', 'POST'])]
    public function tagsNew(Request $request, EntityManagerInterface $entityManager, TagRepository $tagRepository): Response
    {
        $tag = new Tag();
        $tag->setColor('#0ea5e9');

        $form = $this->createForm(TagType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $tag->setPosition($tagRepository->getNextPosition());

            $entityManager->persist($tag);
            $entityManager->flush();

            $this->addFlash('success', 'Tag créé.');

            return $this->redirectToRoute('settings_tags_index');
        }

        return $this->render('settings/tags/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/settings/tags/{id}/edit', name: 'settings_tags_edit', methods: ['GET', 'POST'])]
    public function tagsEdit(Tag $tag, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TagType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Tag mis à jour.');

            return $this->redirectToRoute('settings_tags_index');
        }

        return $this->render('settings/tags/edit.html.twig', [
            'tag' => $tag,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/settings/tags/{id}/delete', name: 'settings_tags_delete', methods: ['POST'])]
    public function tagsDelete(Tag $tag, Request $request, EntityManagerInterface $entityManager, TagRepository $tagRepository): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Token CSRF invalide pour la suppression de tag.');

            return $this->redirectToRoute('settings_tags_index');
        }

        if ($tag->getTasks()->count() > 0) {
            $this->addFlash('error', 'Impossible de supprimer un tag déjà utilisé par des tâches.');

            return $this->redirectToRoute('settings_tags_index');
        }

        $entityManager->remove($tag);
        $entityManager->flush();

        $this->normalizeTagPositions($tagRepository, $entityManager);
        $this->addFlash('success', 'Tag supprimé.');

        return $this->redirectToRoute('settings_tags_index');
    }

    #[Route('/api/settings/tags/reorder', name: 'api_settings_tags_reorder', methods: ['POST'])]
    public function tagsReorder(Request $request, TagRepository $tagRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Payload JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $token = isset($data['_token']) ? (string) $data['_token'] : '';
        if (!$this->isCsrfTokenValid('reorder_tags', $token)) {
            return $this->json(['error' => 'CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $orderedIds = $data['orderedIds'] ?? null;
        if (!is_array($orderedIds)) {
            return $this->json(['error' => 'orderedIds doit être un tableau.'], Response::HTTP_BAD_REQUEST);
        }

        $tags = $tagRepository->findAll();
        if (count($orderedIds) !== count($tags)) {
            return $this->json(['error' => 'orderedIds doit contenir tous les tags.'], Response::HTTP_BAD_REQUEST);
        }

        $tagsById = [];
        foreach ($tags as $tag) {
            $tagsById[$tag->getId()] = $tag;
        }

        foreach ($orderedIds as $position => $id) {
            $id = (int) $id;
            if (!isset($tagsById[$id])) {
                return $this->json(['error' => 'ID de tag inconnu dans orderedIds.'], Response::HTTP_BAD_REQUEST);
            }

            $tagsById[$id]->setPosition($position);
        }

        $entityManager->flush();

        return $this->json(['success' => true]);
    }

    private function normalizeColumnPositions(ColumnRepository $columnRepository, EntityManagerInterface $entityManager): void
    {
        $columns = $columnRepository->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
        foreach ($columns as $position => $column) {
            $column->setPosition($position);
        }

        $entityManager->flush();
    }

    private function normalizeTagPositions(TagRepository $tagRepository, EntityManagerInterface $entityManager): void
    {
        $tags = $tagRepository->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
        foreach ($tags as $position => $tag) {
            $tag->setPosition($position);
        }

        $entityManager->flush();
    }
}
