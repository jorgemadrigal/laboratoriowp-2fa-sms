<?php
/**
 * Cargador de plantilla para WooCommerce.
 *
 * `wc_get_template()` solo sabe incluir un archivo, y a esta plantilla no le
 * pasa argumentos. Este archivo existe únicamente para devolver el control
 * al controlador, que sí sabe qué datos necesita la vista.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

LM2FA_Account_Challenge::render();
