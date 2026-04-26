<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\TaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    normalizationContext: ['groups' => ['task:read']],
    denormalizationContext: ['groups' => ['task:write']],
)]
#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'task')]
#[ORM\Index(name: 'idx_task_column_position', columns: ['column_id', 'position'])]
#[ORM\Index(name: 'idx_task_deadline_at', columns: ['deadline_at'])]
#[ORM\Index(name: 'idx_task_archived_at', columns: ['archived_at'])]
#[ORM\Index(name: 'idx_task_done_at', columns: ['done_at'])]
#[ORM\HasLifecycleCallbacks]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['task:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['task:read', 'task:write'])]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['task:read', 'task:write'])]
    private ?string $description = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 3])]
    #[Assert\Range(min: 1, max: 5)]
    #[Groups(['task:read', 'task:write'])]
    private int $importance = 3;

    #[ORM\Column(type: 'smallint', options: ['default' => 3])]
    #[Assert\Range(min: 1, max: 5)]
    #[Groups(['task:read'])]
    private int $urgency = 3;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['task:read', 'task:write'])]
    private ?\DateTimeImmutable $deadlineAt = null;

    #[ORM\ManyToOne(targetEntity: Column::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['task:read', 'task:write'])]
    private ?Column $column = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\PositiveOrZero]
    #[Groups(['task:read', 'task:write'])]
    private int $position = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['task:read'])]
    private ?\DateTimeImmutable $doneAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['task:read'])]
    private ?\DateTimeImmutable $archivedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['task:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['task:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'tasks')]
    #[ORM\JoinTable(name: 'task_tag')]
    #[Assert\Count(min: 1, minMessage: 'Une tâche doit avoir au moins un tag.')]
    #[Groups(['task:read', 'task:write'])]
    private Collection $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $now = new \DateTimeImmutable();

        if (null === $this->createdAt) {
            $this->createdAt = $now;
        }

        if (null === $this->updatedAt) {
            $this->updatedAt = $now;
        }
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getImportance(): int
    {
        return $this->importance;
    }

    public function setImportance(int $importance): static
    {
        $this->importance = $importance;

        return $this;
    }

    public function getUrgency(): int
    {
        return $this->urgency;
    }

    public function setUrgency(int $urgency): static
    {
        $this->urgency = $urgency;

        return $this;
    }

    public function getDeadlineProgressPercent(?\DateTimeImmutable $now = null): ?int
    {
        if (null === $this->createdAt || null === $this->deadlineAt) {
            return null;
        }

        $now ??= new \DateTimeImmutable();

        $totalSeconds = $this->deadlineAt->getTimestamp() - $this->createdAt->getTimestamp();
        if ($totalSeconds <= 0) {
            return 100;
        }

        $elapsedSeconds = $now->getTimestamp() - $this->createdAt->getTimestamp();
        $ratio = max(0, min(1, $elapsedSeconds / $totalSeconds));

        return (int) round($ratio * 100);
    }

    public function getAutoUrgencyLevel(?\DateTimeImmutable $now = null): int
    {
        if (null === $this->deadlineAt) {
            return 1;
        }

        $now ??= new \DateTimeImmutable();

        if ($now >= $this->deadlineAt) {
            return 5;
        }

        $progress = $this->getDeadlineProgressPercent($now) ?? 0;

        return match (true) {
            $progress >= 80 => 5,
            $progress >= 60 => 4,
            $progress >= 40 => 3,
            $progress >= 20 => 2,
            default => 1,
        };
    }

    #[SerializedName('autoUrgencyLevel')]
    #[Groups(['task:read'])]
    public function getAutoUrgencyLevelForApi(): int
    {
        return $this->getAutoUrgencyLevel();
    }

    public function getDeadlineAt(): ?\DateTimeImmutable
    {
        return $this->deadlineAt;
    }

    public function setDeadlineAt(?\DateTimeImmutable $deadlineAt): static
    {
        $this->deadlineAt = $deadlineAt;

        return $this;
    }

    public function getColumn(): ?Column
    {
        return $this->column;
    }

    public function setColumn(Column $column): static
    {
        $this->column = $column;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getDoneAt(): ?\DateTimeImmutable
    {
        return $this->doneAt;
    }

    public function setDoneAt(?\DateTimeImmutable $doneAt): static
    {
        $this->doneAt = $doneAt;

        return $this;
    }

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function setArchivedAt(?\DateTimeImmutable $archivedAt): static
    {
        $this->archivedAt = $archivedAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }
}
