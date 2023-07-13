<?php

namespace Drupal\rsvplist\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;

/**
 * @Block(
 *  id="rsvp_block",
 *  admin_label=@Translation("The RSVP Block")
 * )
 */

class RSVPBlock extends BlockBase {

    /**
     * {@inheritdoc}
     */

    public function build() {
        /* return [
            '#type' => 'markup',
            '#markup' => $this->t('My RSVP list Block'),
        ] */
        return \Drupal::formBuilder()->getForm('Drupal\rsvplist\Form\RSVPForm');

    }
    /**
     * {@inheritdoc}
     */
    public function blockAccess(AccountInterface $account) {
        $node = \Drupal::routeMatch()->getParameter('node');

        if ( !(is_null($node)) ) {
            $enabler = \Drupal::service('rsvplist.enabler');
            if( $enabler->isEnabled($node) ) {
                return AccessResult::allowedIfHasPermission($account, 'view rsvplist');
            }
        }
        return AccessResult::forbidden();
    }
}