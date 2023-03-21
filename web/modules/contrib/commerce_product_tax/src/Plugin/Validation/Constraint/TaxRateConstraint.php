<?php

namespace Drupal\commerce_product_tax\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Tax rate constraint.
 *
 * @Constraint(
 *   id = "TaxRate",
 *   label = @Translation("Tax rate", context = "Validation"),
 * )
 */
class TaxRateConstraint extends Constraint {

  public $singleTaxRatePerZoneMessage = '%name: cannot select more than one tax rate per zone.';
  public $invalidRateMessage = '%name: the selected tax rate %value is not valid.';
  public $invalidZoneMessage = '%name: the selected tax zone %value is not allowed.';

}
