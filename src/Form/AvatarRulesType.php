<?php

namespace App\Form;

use App\Entity\Avatar;
use App\Enum\Medal;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AvatarRulesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $medalChoices = [];
        foreach (Medal::cases() as $medal) {
            $label = $medal->getTitle() . ' (' . $medal->value . ')';
            $medalChoices[$label] = $medal->value;
        }

        $builder
            ->add('requirement', TextType::class, [
                'required' => false,
            ])
            ->add('reqMedal', ChoiceType::class, [
                'choices' => $medalChoices,
                'placeholder' => 'Nenhuma Medalha',
                'required' => false,
            ])
            ->add('reqTier', ChoiceType::class, [
                'choices' => [
                    'Bronze' => 'bronze',
                    'Prata' => 'silver',
                    'Ouro' => 'gold',
                ],
                'required' => false,
            ])
            ->add('reqGoldCount', IntegerType::class, [
                'required' => false,
            ])
            ->add('reqRankType', ChoiceType::class, [
                'choices' => [
                    'Nenhum Ranking' => null,
                    'Curtidas (Top 3)' => 'likes',
                    'Medalhas (Top 3)' => 'medals',
                ],
                'placeholder' => 'Nenhum Ranking',
                'required' => false,
            ])
            ->add('reqRankPos', ChoiceType::class, [
                'choices' => [
                    'Top 3' => 3,
                    'Top 2' => 2,
                    'Top 1' => 1,
                ],
                'required' => false,
            ])
            ->add('isDefault', CheckboxType::class, [
                'required' => false,
                'false_values' => [null, '0', ''],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Avatar::class,
            'csrf_protection' => false,
        ]);
    }
}
