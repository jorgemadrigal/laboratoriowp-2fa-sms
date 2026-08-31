# LaboratorioWP 2FA por SMS

Segundo factor por SMS para WordPress y WooCommerce. No habla con ninguna
pasarela ni guarda créditos: pide los códigos a la API REST de
[SMS marketing y OTP por SMS](https://laboratoriowp.com/sms-marketing-y-otp-por-sms/)
con una clave API que generas en tu panel de cliente.

- **Versión:** 2.1.0
- **Requiere:** PHP 7.4+, WordPress 6.2+ (WooCommerce opcional)
- **Servicio:** https://laboratoriowp.com/sms-marketing-y-otp-por-sms/
- **Panel de cliente:** https://clientes.laboratoriowp.com/

---

## 1. Mapa del código

Un archivo = una responsabilidad. El arranque no tiene lógica: solo define
constantes y llama a `LM2FA_Plugin::boot()`.

```
laboratoriowp-2fa-sms.php     Constantes + autoloader + hooks de activación
uninstall.php                 Borrado de datos (solo si el admin lo pidió)

includes/                     Núcleo, sin HTML
  class-lm2fa-autoloader.php  LM2FA_Foo_Bar -> class-lm2fa-foo-bar.php
  class-lm2fa-plugin.php      Lista de módulos y en qué hook arranca cada uno
  class-lm2fa-install.php     Activación, cron y migraciones
  class-lm2fa-settings.php    Registro CENTRAL de opciones

  class-lm2fa-util.php        IP, fechas, rate limit, cookies, vistas
  class-lm2fa-phone.php       Normalización de teléfonos MX
  class-lm2fa-log.php         Diario de eventos
  class-lm2fa-notices.php     Avisos de un solo uso entre peticiones

  class-lm2fa-client.php      CLIENTE de la API REST del servidor central
  class-lm2fa-errors.php      Códigos del servidor -> mensajes para la gente

  class-lm2fa-user.php        Estado 2FA del usuario (user_meta)
  class-lm2fa-recovery.php    Códigos de respaldo (HMAC)
  class-lm2fa-devices.php     Equipos de confianza y huellas conocidas
  class-lm2fa-challenge.php   Sesión pendiente entre contraseña y código

  class-lm2fa-verifier.php    Máquina de estados del desafío, sin pantalla
  class-lm2fa-login.php       Pantalla del desafío en wp-login.php
  class-lm2fa-enroll.php      Alta y gestión (controlador de formularios)
  class-lm2fa-email-otp.php   Canal alternativo: código por correo
  class-lm2fa-mailer.php      wp_mail() con plantillas
  class-lm2fa-monitor.php     Vigilancia diaria del saldo
  class-lm2fa-cli.php         Comandos wp lm2fa

admin/                        Back-office
  class-lm2fa-admin.php       Menús, assets, datos de cada pantalla
  class-lm2fa-admin-actions.php  Formularios del admin (POST-Redirect-GET)
  views/page-*.php            HTML de las pantallas
  views/tab-*.php             HTML de las pestañas de ajustes
  views/profile-section.php   Bloque dentro del perfil de usuario

public/                       Front
  class-lm2fa-account.php     Pestaña "Seguridad" en Mi cuenta (WooCommerce)
  class-lm2fa-account-challenge.php  Pantalla del desafío dentro de Mi cuenta
  class-lm2fa-branding.php    Apariencia de wp-login.php
  class-lm2fa-manager.php     Gestor del usuario, reutilizado en los dos sitios
  views/manager-*.php         Fragmentos del gestor
  views/login-challenge.php   Formulario del código (wp-login.php)
  views/account-challenge.php Formulario del código (Mi cuenta)
  views/email-*.php           Plantillas de correo

assets/
  css/admin.css               Back-office
  css/account.css             Mi cuenta (variables del tema)
  css/login.css               Pantalla del código
  js/admin.js                 Selector de logo y color
  js/manager.js               Confirmaciones, copiar/descargar códigos
  js/login.js                 Detalles del formulario del código
```

### Regla de capas

```
vistas  ->  servicios  ->  cliente REST  ->  servidor central
(HTML)      (negocio)      (transporte)
```

- Las **vistas** no consultan nada. Si una vista necesita un dato, se añade
  un método de lectura al modelo y el controlador se lo pasa ya preparado.
- `LM2FA_Client` es el **único** archivo que sabe que existe una API remota.
  Si mañana cambia el namespace o el transporte, se cambia ahí y ya.
- El **código en claro nunca llega a este plugin**: el servidor solo devuelve
  un `request_id`. La única excepción es el código por correo, que se genera
  y se comprueba aquí y no toca el servidor.

---

## 2. Cómo añadir cosas

**Una opción nueva** → una línea en `LM2FA_Settings::registry()`. De ahí salen
el valor por defecto, la sanitización y el borrado en la desinstalación.
Luego pinta el campo en la pestaña que toque.

**Una pestaña de ajustes** → una entrada en `LM2FA_Admin::tabs()`, un grupo en
`LM2FA_Settings` y un archivo `admin/views/tab-loquesea.php`.

**Una acción del usuario** → una entrada en `LM2FA_Enroll::handlers()` y un
método que devuelva `array( estado, mensaje )`.

**Una acción del admin** → una entrada en `LM2FA_Admin_Actions::handlers()`
con la capacidad requerida. El nonce se llama igual que la acción.

**Una clase nueva** → créala en `includes/`, `admin/` o `public/` siguiendo la
convención `class-lm2fa-lo-que-sea.php`. El autoloader la encuentra sola.

---

## 3. La API que se consume

Namespace `lm-saas/v1`, autenticación por cabecera `X-API-KEY`.

| Endpoint | Uso en este plugin |
| --- | --- |
| `GET /account` | Botón "Probar la conexión" |
| `POST /otp/request` | Alta, acceso y reenvíos. Devuelve `request_id` |
| `POST /otp/verify` | Comprobación del código escrito |
| `GET /otp/quota` | Saldo del panel y vigilancia diaria (caché de 5 min) |

Del otro lado está `laboratoriowpsms-saas-pro` (v15.0.0 o superior, constante
`LM2FA_Client::MIN_SERVER`). `GET /account` declara su versión y se anota: si
se queda por debajo, el administrador ve un aviso en el escritorio.

Lo que el servidor decide y este plugin acata en vez de suponer:

- **Vigencia del código.** `expires_in` viene en cada `/otp/request` y con él
  se ajusta lo que dura la sesión pendiente (`LM2FA_Challenge::sync_expiry()`)
  y el alta a medias. Antes eran 10 minutos fijos, y con un `lm_otp_ttl` más
  largo el formulario caducaba antes que el código.
- **Numeración.** `LM2FA_Phone::normalize()` replica caso por caso la cadena
  de `LM_Phone::normalize()`: se acepta y se rechaza exactamente lo mismo. Se
  guarda en nacional (10 dígitos) porque es lo que teclea el usuario; el
  servidor trabaja en internacional.
- **Saldo.** El servidor manda `quota_status` en tres sitios —`/otp/quota`,
  la respuesta de `/otp/request` y el error 402 de "sin saldo"—. Los tres
  pasan por el mismo sitio y disparan `lm2fa_quota_updated`, así que el aviso
  de saldo bajo salta en el momento y no en la siguiente pasada diaria.
- **Enmascarado.** El servidor devuelve `5255******78`; en pantalla se
  reescribe como `55******78` para no mezclar dos formatos del mismo número.

Los códigos de error del servidor (`lm_otp_expired`, `lm_otp_no_balance`…) se
traducen en `LM2FA_Errors`. Si el servidor añade uno nuevo, es la única lista
que hay que ampliar.

Si el servidor tiene enlaces permanentes planos, el cliente reintenta la
llamada por `?rest_route=` automáticamente.

---

## 4. Recorrido del acceso

```
wp_login              la contraseña ya es correcta
  ├─ ¿requiere 2FA?   no  -> se deja pasar
  ├─ ¿equipo fiable?  sí  -> se deja pasar
  └─ sí:
     wp_destroy_current_session() + wp_clear_auth_cookie()
     LM2FA_Challenge::open()      sesión pendiente, 10 min
     POST /otp/request            SMS con el código
     redirect a la pantalla de origen (ver más abajo)

formulario del código      -> LM2FA_Verifier
  ├─ código SMS       -> POST /otp/verify
  ├─ código de correo -> se comprueba aquí (si está habilitado)
  ├─ código respaldo  -> HMAC contra user_meta
  └─ acierto: wp_set_auth_cookie() y a donde iba
```

### Dónde se pinta el desafío

El usuario termina de identificarse donde empezó:

| Entró por | Pantalla | Destino final |
| --- | --- | --- |
| `wp-login.php` | `LM2FA_Login` | `redirect_to`, filtro `login_redirect` |
| Formulario de Mi cuenta o del checkout | `LM2FA_Account_Challenge` | `$_POST['redirect']`, filtro `woocommerce_login_redirect` |

Mandar a un cliente de la tienda a `wp-login.php` rompe la marca y parece
otro sitio. La pantalla de Mi cuenta sustituye la plantilla
`myaccount/form-login.php` a través del filtro `wc_get_template`, así que el
desafío hereda la plantilla del tema, su cabecera y su pie; el resto de la
página no se toca.

**La lógica no se duplica.** `LM2FA_Verifier` resuelve el POST —reenviar,
cambiar de canal, código de respaldo, verificar— y devuelve un resultado sin
imprimir ni redirigir. Cada pantalla solo decide cómo pintarlo y a dónde
mandar al usuario. Para añadir una tercera pantalla basta con eso.

**El token de sesión se destruye, no solo la cookie.** `wp_login` se dispara
después de que WordPress haya creado la sesión: borrar únicamente la cookie
del navegador dejaba viva una sesión que la contraseña sola había abierto.

---

## 5. Vías que se saltarían el segundo factor

XML-RPC y las contraseñas de aplicación autentican sin poder mostrar un
formulario. Con *Comportamiento → Accesos heredados* activo (por defecto) se
rechazan para las cuentas protegidas. Desactívalo solo si dependes de ellas.

---

## 6. Cuando el SMS no llega

Por orden de preferencia:

1. **Reenviar el SMS** (máximo 2 veces por sesión).
2. **Código por correo**, si el administrador lo habilitó. Lo genera y lo
   comprueba este sitio: no consume saldo y el servidor central ni se entera.
   Es más débil que el SMS —quien controle el buzón entra— pero evita que una
   avería de la pasarela deje a alguien fuera.
3. **Código de respaldo**, de los que se entregan al activar.
4. **Desde el servidor**, cuando ya no queda nada:

```
wp lm2fa disable <usuario>   # apaga el 2FA, conserva el teléfono
wp lm2fa reset   <usuario>   # borra todo, empieza de cero
wp lm2fa status  [<usuario>] # diagnóstico
wp lm2fa quota               # saldo en vivo
```

---

## 7. Saldo

Sin saldo en el servidor, quien tenga el segundo factor activo no recibe su
código y no entra. Por eso hay una tarea diaria (`lm2fa_daily_check`) que
consulta `/otp/quota`, marca el estado y avisa al administrador —banda en el
escritorio y correo— cuando baja del umbral configurado.

Además, cualquier lectura fresca del saldo se evalúa al vuelo: el error 402
de una solicitud OTP trae el `quota_status` del momento, así que el aviso
aparece en cuanto el servidor lo dice.

Se distingue quedarse sin saldo (`can_send`) de que el proveedor haya apagado
el servicio (`enabled`): el consejo no es el mismo, porque comprar créditos no
reactiva un servicio desactivado.

---

## 8. Ganchos

```php
apply_filters( 'lm2fa_requires_challenge', $required, $user );  // ¿pedir código?
apply_filters( 'lm2fa_use_account_screen', $claims );           // desafío en el front
apply_filters( 'lm2fa_normalize_phone', $phone, $raw );         // otras numeraciones
apply_filters( 'lm2fa_panel_path', $path );                     // ruta del panel en el servidor
apply_filters( 'lm2fa_panel_url', $url, $tab );                 // enlace completo al panel
do_action( 'lm2fa_quota_updated', $quota );                     // lectura fresca del saldo
do_action( 'lm2fa_login_verified', $user );                     // acceso completado
do_action( 'lm2fa_enrolled', $user_id );
do_action( 'lm2fa_disabled', $user_id );
do_action( 'lm2fa_reset', $user_id );
do_action( 'lm2fa_event', $type, $detail, $user_id );           // reenviar el log
do_action( 'lm2fa_loaded' );                                    // clases disponibles
```
