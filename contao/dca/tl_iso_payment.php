<?php

use Contao\System;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

$GLOBALS['TL_DCA']['tl_iso_payment']['palettes']['stripe'] = '{type_legend},name,label,type;{note_legend:hide},note;{stripe_legend},stripe_api_key,stripe_endpoint_secret;{config_legend},new_order_status,quantity_mode,minimum_quantity,maximum_quantity,minimum_total,maximum_total,countries,shipping_modules,product_types,product_types_condition,config_ids;{price_legend:hide},price,tax_class;{expert_legend:hide},guests,protected;{enabled_legend},enabled';


$GLOBALS['TL_DCA']['tl_iso_payment']['fields']['stripe_api_key'] = array
(
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
    'sql'       => "varchar(255) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_iso_payment']['fields']['stripe_endpoint_secret'] = array
(
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => array('mandatory'=>false, 'maxlength'=>255, 'tl_class'=>'w50'),
    'sql'       => "varchar(255) NOT NULL default ''"
);