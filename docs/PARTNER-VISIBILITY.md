# SONIKA Partners — visibilidad pública y modo catálogo

## Regla

Un perfil personalizado solo se publica cuando **las dos condiciones** están activas:

1. Interruptor global `affiliate_public_profiles_enabled` = `1`.
2. Checkbox del Partner `public_profile_enabled` = `1`.

Si cualquiera está apagado, `/artista/{slug}` y la URL vanidad `/{slug}` redirigen a `/r/{code}`.
Ese enlace registra el clic, fija la cookie `sonika_aff` durante 180 días y lleva al catálogo
original de SONIKA con `?ref={code}`. No se muestran fotos, videos ni trayectoria.

## Valores por defecto

- Global: `0` (modo catálogo).
- Partner: `0` (oculto hasta que lo autorice).
- El dueño autenticado siempre puede previsualizar su perfil, con `noindex` y un aviso privado.

## Panel del Partner

En **Mi Perfil → Visibilidad pública**:

- Switch “Mostrar mi perfil público”.
- Estado real: Visible / Oculto.
- Explicación de qué se publica en cada caso.
- Link de atribución con botón para copiar.
- Enlace “Ver lo que ve el público”.

Cuando el global está apagado, el Partner puede dejar su preferencia marcada. El mensaje
aclara que el perfil seguirá oculto hasta que SONIKA active los perfiles personalizados.

## Admin

`/admin-afiliados` incluye:

- Interruptor global.
- Estado “Modo catálogo” / “Activo”.
- Checkbox de visibilidad por Partner.
- Estado efectivo por Partner (Visible / Oculto), considerando el global.

## Rutas y atribución

- `storePath()` devuelve `/artista/{slug}` solo si el perfil es visible; si no, `/tienda`.
- `publicPath()` devuelve la página propia si es visible o `/r/{code}` si está oculta.
- El panel, el catálogo y el CSV siguen usando `/r/{code}` como enlace de atribución.
- El Directorio Sonika oculta la foto de los perfiles no visibles y ofrece “Ver catálogo con mi link”.

## Migración

`install/affiliates_visibility.sql` crea el setting global en `0`. La desactivación masiva
de perfiles existentes es una migración única; está comentada en el SQL para no resetear
preferencias en cada despliegue.
