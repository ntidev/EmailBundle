<?php

namespace NTI\EmailBundle\Form;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SmtpType extends AbstractType
{

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add("host", TextType::class, array("label" => "Host"))
            ->add("port", TextType::class, array("label" => "Port"))
            ->add("encryption", TextType::class, array("label" => "Encryption"))
            ->add("user", TextType::class, array("label" => "User"))
            ->add("password", TextType::class, array("label" => "Password"))
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'NTI\EmailBundle\Entity\Smtp',
            'allow_extra_fields' => true,
            'csrf_protection' => false,
            'cascade_validation' => true,
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'nti_smtp_smtp';
    }


}
