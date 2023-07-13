<?php

namespace Drupal\rsvplist\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class RSVPSettingsForm extends ConfigFormBase {
    
    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'rsvplist_admin_settings';
    }

    /**
     * {@inheritdoc}
     */
    public function getEditableConfigNames()
    {
        return [
            'rsvplist.settings',
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $types = node_type_get_names();

        $config = $this->config('rsvplist.settings');
        $form['rsvplist_types'] = [
            '#type' => 'checkboxes',
            '#title' => $this->t('The content types to enable the RSVP list collection for'),
            '#default_value' => $config->get('allowed_types'),
            '#options' => $types,
            '#description' => $this->t('On the specified node types, an RSVP option will be available and can be enabled while the node is being edited.'),
        ];
        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm( array &$form, FormStateInterface $form_state){
        $selected_allowed_types = array_filter( $form_state->getValue('rsvplist_types' ) );
        sort($selected_allowed_types);

        $this->config('rsvplist.settings')
            ->set('allowed_types',$selected_allowed_types)
            ->save();
        
        parent::submitForm($form, $form_state);
    }
}