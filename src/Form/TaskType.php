<?php

namespace App\Form;

use App\Entity\Tag;
use App\Entity\Task;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class TaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
            ])
            ->add('tags', EntityType::class, [
                'class' => Tag::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'label' => 'Tags (au moins un)',
                'choice_attr' => static function (Tag $tag): array {
                    return [
                        'data-color' => $tag->getColor(),
                    ];
                },
            ])
            ->add('importance', IntegerType::class, [
                'label' => 'Importance (1-5)',
                'attr' => ['min' => 1, 'max' => 5],
            ]);

        if (($options['creation_mode'] ?? false) === true) {
            $builder
                ->add('deadlineMode', ChoiceType::class, [
                    'mapped' => false,
                    'label' => 'Mode deadline',
                    'choices' => [
                        'Durée + unité' => 'duration',
                        'Date / heure' => 'date',
                    ],
                    'data' => 'duration',
                    'constraints' => [
                        new NotBlank(),
                    ],
                ])
                ->add('deadlineAmount', IntegerType::class, [
                    'mapped' => false,
                    'label' => 'Duree (1-9)',
                    'attr' => ['min' => 1, 'max' => 9],
                    'data' => 3,
                    'required' => false,
                    'constraints' => [
                        new Range(min: 1, max: 9),
                    ],
                ])
                ->add('deadlineUnit', ChoiceType::class, [
                    'mapped' => false,
                    'label' => 'Unite',
                    'choices' => [
                        'Jour' => 'day',
                        'Mois' => 'month',
                    ],
                    'data' => 'day',
                    'required' => false,
                ])
                ->add('deadlineAt', DateTimeType::class, [
                    'label' => 'Deadline (date / heure)',
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'required' => false,
                ]);
        } else {
            $builder->add('deadlineAt', DateTimeType::class, [
                'label' => 'Deadline',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
            'creation_mode' => false,
        ]);

        $resolver->setAllowedTypes('creation_mode', 'bool');
    }
}
