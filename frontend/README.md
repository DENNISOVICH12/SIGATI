# Frontend SIGATI: URL pública y pruebas QR

## Desarrollo en la red local

La identidad permanente del activo es su `public_token` (UUID); el origen incluido en el QR solo indica dónde está desplegado SIGATI. En desarrollo, si `VITE_PUBLIC_APP_URL` está vacío, la etiqueta usa el origen exacto con el que se abrió la aplicación (`window.location.origin`). Por eso un cambio de red no requiere editar código, `.env` ni el activo.

Inicie Laravel (Vite accede a este puerto desde el mismo computador):

```powershell
cd C:\SIGATI\backend
php artisan serve --host=127.0.0.1 --port=8000
```

Inicie Vite exponiéndolo a la LAN:

```powershell
cd C:\SIGATI\frontend
npm run dev -- --host 0.0.0.0
```

Abra SIGATI usando la dirección **Network** que muestra Vite, no la dirección **Local**. Las peticiones relativas a `/api` llegan a Laravel mediante el proxy de Vite; el teléfono solo necesita acceso al puerto de Vite y no es necesario exponer el puerto 8000 a la LAN. Si se configura `VITE_API_BASE_URL` con otro despliegue, esa URL explícita conserva prioridad.

Si SIGATI se abre mediante `localhost` o `127.0.0.1`, el QR sigue disponible para desarrollo, pero la interfaz advierte que no será accesible desde otro dispositivo ni debe imprimirse como etiqueta definitiva.

## Producción y etiquetas definitivas

Configure en el build del frontend una URL institucional estable, sin incluir `/a/{UUID}`:

```dotenv
VITE_PUBLIC_APP_URL=https://sigati.dominio-institucional
```

La URL configurada tiene prioridad sobre el origen del navegador y acepta una barra final, que se normaliza. En producción, el servidor web debe enviar `/api` a Laravel, o se debe establecer `VITE_API_BASE_URL` con la URL absoluta apropiada.

No imprima etiquetas definitivas con `localhost`, `127.0.0.1`, una IP dinámica de portátil o una dirección temporal de hotspot. Una etiqueta es permanente cuando combina el UUID permanente del activo con el hostname estable del despliegue. Los QR generados con orígenes dinámicos son únicamente para pruebas.

## Prueba manual desde iPhone

1. Conecte el iPhone y el PC a una red que permita comunicación entre dispositivos.
2. Inicie Laravel y Vite con los comandos anteriores.
3. En el PC, abra la dirección **Network** publicada por Vite e inicie sesión.
4. Entre a **Activos**, abra un activo y expanda **Identificación QR**.
5. Escanee el QR y confirme que Safari abre `http://IP-ACTUAL:5173/a/{UUID}` sin mostrar el login.
6. Confirme que aparece la ficha pública y envíe un reporte.
7. Verifique en **Mesa de Ayuda** que se creó el ticket.
8. Modifique un dato permitido del activo, vuelva a escanear y confirme que la ficha muestra el dato actualizado y conserva el mismo UUID.
9. Al cambiar de red, reinicie Vite si fuera necesario y abra la nueva dirección **Network**. El QR de prueba usará el nuevo origen con el mismo UUID, sin editar `.env`, código ni activo.
