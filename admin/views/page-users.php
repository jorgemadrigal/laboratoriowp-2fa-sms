<?php
/**
 * Estado del segundo factor usuario por usuario.
 *
 * @var array[] $rows
 * @var int     $total
 * @var int     $paged
 * @var int     $pages
 * @var string  $search
 * @var string  $filter
 * @var bool    $can_edit
 * @var bool    $reset
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
?>
<div class="wrap lm2fa-admin">
  <h1><?php esc_html_e( 'Estado del 2FA por usuario', 'lmsms-2fa' ); ?></h1>

  <?php if ( $reset ) : ?>
    <div class="notice notice-success is-dismissible">
      <p><?php esc_html_e( 'Se restableció la configuración del usuario.', 'lmsms-2fa' ); ?></p>
    </div>
  <?php endif; ?>

  <form method="get" class="lm2fa-filters">
    <input type="hidden" name="page" value="<?php echo esc_attr( LM2FA_Admin::PAGE_USERS ); ?>">

    <label class="screen-reader-text" for="lm2fa-search"><?php esc_html_e( 'Buscar usuarios', 'lmsms-2fa' ); ?></label>
    <input type="search" id="lm2fa-search" name="s" value="<?php echo esc_attr( $search ); ?>"
      placeholder="<?php esc_attr_e( 'Usuario o correo', 'lmsms-2fa' ); ?>">

    <label class="screen-reader-text" for="lm2fa-estado"><?php esc_html_e( 'Estado', 'lmsms-2fa' ); ?></label>
    <select id="lm2fa-estado" name="estado">
      <option value=""><?php esc_html_e( 'Todos los estados', 'lmsms-2fa' ); ?></option>
      <option value="activa" <?php selected( 'activa', $filter ); ?>><?php esc_html_e( 'Verificación activa', 'lmsms-2fa' ); ?></option>
      <option value="inactiva" <?php selected( 'inactiva', $filter ); ?>><?php esc_html_e( 'Verificación inactiva', 'lmsms-2fa' ); ?></option>
    </select>

    <button type="submit" class="button"><?php esc_html_e( 'Filtrar', 'lmsms-2fa' ); ?></button>

    <span class="lm2fa-hint">
      <?php
      printf(
        /* translators: %s número de usuarios encontrados. */
        esc_html( _n( '%s usuario', '%s usuarios', $total, 'lmsms-2fa' ) ),
        esc_html( number_format_i18n( $total ) )
      );
      ?>
    </span>
  </form>

  <table class="wp-list-table widefat fixed striped">
    <thead>
      <tr>
        <th><?php esc_html_e( 'Usuario', 'lmsms-2fa' ); ?></th>
        <th><?php esc_html_e( 'Rol', 'lmsms-2fa' ); ?></th>
        <th><?php esc_html_e( 'Estado', 'lmsms-2fa' ); ?></th>
        <th><?php esc_html_e( 'Teléfono', 'lmsms-2fa' ); ?></th>
        <th><?php esc_html_e( 'Respaldos', 'lmsms-2fa' ); ?></th>
        <th><?php esc_html_e( 'Equipos', 'lmsms-2fa' ); ?></th>
        <th><?php esc_html_e( 'Último acceso verificado', 'lmsms-2fa' ); ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if ( empty( $rows ) ) : ?>
        <tr><td colspan="8"><?php esc_html_e( 'Ningún usuario coincide con la búsqueda.', 'lmsms-2fa' ); ?></td></tr>
      <?php endif; ?>

      <?php foreach ( $rows as $row ) : ?>
        <tr>
          <td>
            <strong><a href="<?php echo esc_url( get_edit_user_link( $row['id'] ) ); ?>"><?php echo esc_html( $row['login'] ); ?></a></strong><br>
            <span class="description"><?php echo esc_html( $row['email'] ); ?></span>
          </td>
          <td><?php echo esc_html( $row['roles'] ); ?></td>
          <td>
            <?php if ( $row['active'] ) : ?>
              <span class="lm2fa-badge is-on"><?php esc_html_e( 'Activa', 'lmsms-2fa' ); ?></span>
            <?php else : ?>
              <span class="lm2fa-badge is-off"><?php esc_html_e( 'Inactiva', 'lmsms-2fa' ); ?></span>
            <?php endif; ?>

            <?php if ( $row['enforced'] ) : ?>
              <br><span class="description"><?php esc_html_e( 'Obligatoria por rol', 'lmsms-2fa' ); ?></span>
            <?php endif; ?>
          </td>
          <td><?php echo esc_html( $row['phone'] ); ?></td>
          <td><?php echo esc_html( $row['codes_left'] ); ?></td>
          <td><?php echo esc_html( $row['devices'] ); ?></td>
          <td><?php echo esc_html( $row['last_auth'] ); ?></td>
          <td>
            <?php if ( $can_edit && $row['has_phone'] ) : ?>
              <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                data-lm2fa-confirm="<?php esc_attr_e( 'Se eliminará su teléfono y sus códigos. El usuario deberá registrarse de nuevo. ¿Continuar?', 'lmsms-2fa' ); ?>">
                <?php wp_nonce_field( 'lm2fa_reset_user' ); ?>
                <input type="hidden" name="action" value="lm2fa_reset_user">
                <input type="hidden" name="user_id" value="<?php echo esc_attr( $row['id'] ); ?>">
                <button type="submit" class="lm2fa-link is-danger"><?php esc_html_e( 'Restablecer', 'lmsms-2fa' ); ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ( $pages > 1 ) : ?>
    <div class="tablenav">
      <div class="tablenav-pages">
        <?php
        echo wp_kses_post(
          paginate_links(
            array(
              'base'    => add_query_arg( 'paged', '%#%' ),
              'format'  => '',
              'current' => $paged,
              'total'   => $pages,
            )
          )
        );
        ?>
      </div>
    </div>
  <?php endif; ?>

  <p class="lm2fa-hint">
    <?php esc_html_e( 'Si un usuario pierde el acceso al teléfono y no tiene códigos de respaldo, usa "Restablecer" para que pueda registrar un número nuevo.', 'lmsms-2fa' ); ?>
  </p>
</div>
