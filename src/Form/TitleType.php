<?php

namespace App\Form;

use App\Entity\Title;
use App\Enum\Medal;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TitleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $medalChoices = [];
        foreach (Medal::cases() as $medal) {
            $label = $medal->getTitle() . ' (' . $medal->value . ')';
            $medalChoices[$label] = $medal->value;
        }

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nome do Título',
                'required' => true,
            ])
            ->add('ribbon', ChoiceType::class, [
                'label' => 'Ribbon (Faixa)',
                'required' => true,
                'choices' => [
                    'Alert Ribbon (Padrão)' => 'alert-ribbon.png',
                    'Effort Ribbon' => 'effort-ribbon.png',
                    'Classic Ribbon' => 'classic-ribbon.png',
                    'Best Friends Ribbon' => 'best-friends-ribbon.png',
                    'Gorgeous Royal Ribbon' => 'gorgeous-royal-ribbon.png',
                    'Souvenir Ribbon' => 'souvenir-ribbon.png',
                    'Artist Ribbon' => 'artist-ribbon.png',
                    'Tower Master Ribbon' => 'tower-master-ribbon.png',
                    'Galar Champion Ribbon' => 'galar-champion-ribbon.png',
                    'Champion Ribbon' => 'champion-ribbon.png',
                    'Battle Champion Ribbon' => 'battle-champion-ribbon.png',
                    'Master Rank Ribbon' => 'master-rank-ribbon.png',
                ],
            ])
            ->add('requirement', TextType::class, [
                'label' => 'Descrição do Requisito',
                'required' => true,
            ])
            ->add('isDefault', CheckboxType::class, [
                'label' => 'Disponível por padrão (sem requisitos)?',
                'required' => false,
            ])
            ->add('reqMedal', ChoiceType::class, [
                'label' => 'Medalha Necessária',
                'required' => false,
                'placeholder' => 'Nenhuma medalha',
                'choices' => $medalChoices,
            ])
            ->add('reqTier', ChoiceType::class, [
                'label' => 'Nível da Medalha',
                'required' => false,
                'placeholder' => 'Nenhum nível',
                'choices' => [
                    'Bronze' => 'bronze',
                    'Prata' => 'silver',
                    'Ouro' => 'gold',
                ],
            ])
            ->add('reqGoldCount', IntegerType::class, [
                'label' => 'Quantidade de Medalhas de Ouro necessárias',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Title::class,
        ]);
    }
}
