<?php

namespace Drupal\rsvplist\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class RSVPForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    function getFormId() {
        return 'rsvplist_email_form';
    }
    /**
     * {@inheritdoc}
     */
    function buildForm(array $form, FormStateInterface $form_state) {

        //Attempt to get the fully loaded node object of the viewed page
        $node = \Drupal::routeMatch()->getParameter('node');
        // Some pages may not be nodes and $node will be null on those pages.
        // If a node was loaded, get the node id.
        if ( !(is_null($node)))
            $nid = $node->ID();
        else 
            $nid = 0;
        // Establish the form render array. I has an email text field,
        // a submit button, and a hidden field containing the node ID.
        $form['email'] = [
            '#type' => 'textfield',
            '#title' => t('Email address'),
            '#size' => 25,
            '#description' => t("We will send updates to the email address you provide."),
            '#required' => TRUE,
        ];
        $form['submit'] = [
            '#type' => 'submit',
            '#value' => t('RSVP'),
        ];

        $form['nid'] = [
            '#type' => 'hidden',
            '#value' => $nid
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm( array &$form, FormStateInterface $form_state) {
        $value = $form_state->getValue('email');
        if ( !(\Drupal::service('email.validator')->isValid($value)) ) {
            $form_state->setErrorByName('email',
                $this->t('It appears that %mail is not a valid email. Please try again', ['%mail'=>$value])
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm( array &$form, FormStateInterface $form_state) {
        //$submitted_email = $form_state->getValue('email');
        //$this->messenger()->addMessage(t( "The form is working! You entered @entry." ,[ '@entry' => $submitted_email ]));
        try {
            //Begin Phase1: initiate variables to save

            // Get current user ID.
            $uid = \Drupal::currentUser()->id();
            //Demonstration for how to load a full user object of the current user. This $full_user variable is not needed for this code but is shown for demonstration purposes.
            $full_user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
            //Obtain values as entered into the form
            $nid = $form_state->getValue('nid');
            $email = $form_state->getValue('email');
            $current_time = \Drupal::time()->getRequestTime();
            
            $query = \Drupal::database()->insert('rsvplist');
            $query->fields(['uid', 'nid', 'email', 'created']);
            $query->values([$uid, $nid, $email, $current_time]);
            $query->execute();
            \Drupal::messenger()->addMessage(
                t('Thank you for your RSVP, you are on the list for the event!')
            );
        } catch (\Exception $e) {
            \Drupal::messenger()->addError(
                t('Unable to save RSVP settings at this time due to this database error: @error',['@error'=>$e->getMessage()])
            );
        }
    }
}