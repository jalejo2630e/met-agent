<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Cómo usar" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-[#33475b]">
                Cómo usar
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-10 px-4 sm:px-6 lg:px-8">
                <p class="text-lg text-[#425b76]">
                    Manual de uso de la plataforma Administrador de Agentes IA. Aquí encontrarás, paso a paso, dónde está cada módulo, cómo usarlo y las funcionalidades recientes.
                </p>

                <!-- Roles y visibilidad -->
                <section class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <h3 class="font-semibold text-[#33475b]">Quién ve qué (roles)</h3>
                    <p class="mt-1 text-sm text-[#425b76]">
                        Algunas secciones son <strong>solo para administradores</strong>. Si no eres administrador, no verás las pestañas técnicas ni la configuración global.
                    </p>
                    <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-[#425b76]">
                        <li><strong>Todos los usuarios:</strong> Dashboard, Empresas, Clientes (Lista y Campos dinámicos), Configuración de la empresa, Formulario y Reportes.</li>
                        <li><strong>Solo administrador:</strong> grupos <strong>Integración</strong>, <strong>Comunicación</strong> y <strong>Logs</strong>, la sub-pestaña <strong>Fuente</strong>, la <strong>Configuración global</strong> y la gestión de <strong>Usuarios</strong>.</li>
                    </ul>
                </section>

                <!-- Navegación rápida -->
                <section class="rounded-lg border border-[#e3e8ee] bg-[#f5f8fa] p-4">
                    <h3 class="font-semibold text-[#33475b]">Navegación rápida</h3>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <a href="#inicio" class="rounded bg-white px-3 py-1 text-sm text-[#425b76] shadow-sm hover:bg-[#e3e8ee]">Primeros pasos</a>
                        <a href="#dashboard" class="rounded bg-white px-3 py-1 text-sm text-[#425b76] shadow-sm hover:bg-[#e3e8ee]">Dashboard</a>
                        <a href="#agentes" class="rounded bg-white px-3 py-1 text-sm text-[#425b76] shadow-sm hover:bg-[#e3e8ee]">Empresas</a>
                        <a href="#clientes" class="rounded bg-white px-3 py-1 text-sm text-[#425b76] shadow-sm hover:bg-[#e3e8ee]">Clientes</a>
                        <a href="#comunicacion" class="rounded bg-white px-3 py-1 text-sm text-[#425b76] shadow-sm hover:bg-[#e3e8ee]">Comunicación</a>
                        <a href="#formulario" class="rounded bg-white px-3 py-1 text-sm text-[#425b76] shadow-sm hover:bg-[#e3e8ee]">Formulario</a>
                        <a href="#alertas" class="rounded bg-white px-3 py-1 text-sm text-[#425b76] shadow-sm hover:bg-[#e3e8ee]">Alertas de llamada</a>
                        <a href="#apis" class="rounded bg-white px-3 py-1 text-sm text-[#425b76] shadow-sm hover:bg-[#e3e8ee]">APIs</a>
                    </div>
                </section>

                <!-- Primeros pasos -->
                <section id="inicio" class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                    <div class="border-b border-[#e3e8ee] bg-[#f5f8fa] px-6 py-4">
                        <h3 class="text-lg font-semibold text-[#33475b]">Primeros pasos</h3>
                        <p class="mt-1 text-sm text-[#425b76]">Flujo recomendado para empezar de cero.</p>
                    </div>
                    <div class="space-y-4 p-6">
                        <ol class="list-inside list-decimal space-y-2 text-[#425b76]">
                            <li><strong>Crea una empresa:</strong> Menú → Empresas → «Crear Empresa». Define nombre, descripción y estado.</li>
                            <li><strong>Carga tus clientes:</strong> en la empresa, pestaña <strong>Clientes</strong>. Créalos manualmente, impórtalos desde Excel/CSV o conéctalos por API/endpoint de fuente.</li>
                            <li><strong>Define campos dinámicos</strong> (opcional): información extra por cliente (ciudad, estado, etc.) para filtrar y segmentar.</li>
                            <li><strong>Configura la comunicación</strong> (administrador): en el grupo <strong>Comunicación</strong> define webhooks, plantillas, reglas y horarios de WhatsApp y llamadas.</li>
                            <li><strong>Revisa resultados:</strong> consulta <strong>Reportes</strong> y el detalle de cada cliente (llamadas, notas y <strong>alertas de llamada</strong>).</li>
                            <li><strong>Configura las alertas:</strong> en <strong>Configuración global</strong> define las categorías de alerta y los correos que recibirán las notificaciones.</li>
                        </ol>
                    </div>
                </section>

                <!-- Dashboard -->
                <section id="dashboard" class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                    <div class="border-b border-[#e3e8ee] bg-[#f5f8fa] px-6 py-4">
                        <h3 class="text-lg font-semibold text-[#33475b]">Dashboard</h3>
                        <p class="mt-1 text-sm text-[#425b76]">
                            <strong>Dónde:</strong> Menú principal → Dashboard (o ruta /dashboard)
                        </p>
                    </div>
                    <div class="space-y-4 p-6">
                        <p class="text-[#33475b]">
                            Panel principal tras iniciar sesión. Muestra un resumen visual de la actividad:
                        </p>
                        <ul class="list-inside list-disc space-y-2 text-[#425b76]">
                            <li><strong>Tarjetas de estadísticas:</strong> Empresas, clientes, contactos por WhatsApp y por llamada.</li>
                            <li><strong>Gráfica de barras:</strong> Distribución de empresas, clientes y contactos.</li>
                            <li><strong>Gráfica de dona:</strong> Proporción de contactos por canal (WhatsApp vs Llamada).</li>
                        </ul>
                        <p class="text-sm text-[#425b76]">
                            Haz clic en la tarjeta de "Empresas" para acceder al listado.
                        </p>
                    </div>
                </section>

                <!-- Empresas -->
                <section id="agentes" class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                    <div class="border-b border-[#e3e8ee] bg-[#f5f8fa] px-6 py-4">
                        <h3 class="text-lg font-semibold text-[#33475b]">Empresas</h3>
                        <p class="mt-1 text-sm text-[#425b76]">
                            <strong>Dónde:</strong> Menú → Empresas (o ruta /agents)
                        </p>
                    </div>
                    <div class="space-y-6 p-6">
                        <p class="text-[#33475b]">
                            Las empresas son el núcleo de la plataforma. Cada empresa tiene su propia configuración de clientes y comunicación. Al hacer clic en una empresa, accedes a sus pestañas de configuración.
                        </p>
                        <p class="text-sm text-[#425b76]">
                            <strong>Listado:</strong> Busca por nombre, filtra por estado (Activo, Borrador, Cancelado) y alterna entre vista cuadrícula o lista. Usa "Crear Empresa" para agregar una nueva.
                        </p>
                    </div>
                </section>

                <!-- Clientes -->
                <section id="clientes" class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                    <div class="border-b border-[#e3e8ee] bg-[#f5f8fa] px-6 py-4">
                        <h3 class="text-lg font-semibold text-[#33475b]">Módulo Clientes</h3>
                        <p class="mt-1 text-sm text-[#425b76]">
                            <strong>Dónde:</strong> Empresa → pestaña <strong>Clientes</strong> (grupo con 3 sub-pestañas)
                        </p>
                    </div>
                    <div class="space-y-6 p-6">
                        <h4 class="font-medium text-[#33475b]">Lista</h4>
                        <p class="text-[#425b76]">
                            Gestiona los clientes de la empresa. Puedes crear, editar y eliminar clientes manualmente. Acciones disponibles:
                        </p>
                        <ul class="list-inside list-disc space-y-1 text-[#425b76]">
                            <li><strong>Contactar por WhatsApp:</strong> Abre el modal del cliente con historial de chat y botón para enviar al webhook. Puedes seleccionar la plantilla a enviar.</li>
                            <li><strong>Iniciar llamada:</strong> Envía los datos del cliente al webhook de llamadas.</li>
                            <li><strong>Acciones masivas:</strong> Selecciona varios clientes y ejecuta "Ejecutar ahora" o "Programar según horarios". Para WhatsApp, puedes elegir la plantilla.</li>
                            <li><strong>Ver solicitudes de callback:</strong> En el modal de WhatsApp de cada cliente se muestra el registro de callbacks programados (pendientes, completados, cancelados).</li>
                        </ul>

                        <h4 class="font-medium text-[#33475b]">Campos dinámicos</h4>
                        <p class="text-[#425b76]">
                            Define campos adicionales para los clientes (ej: estado, ciudad, fecha_activación). Estos campos aparecen al crear/editar clientes y se guardan en <code class="rounded bg-[#e3e8ee] px-1">custom_fields</code>. Útiles para reglas de ejecución y retrasos.
                        </p>

                        <h4 class="font-medium text-[#33475b]">Fuente <span class="text-xs font-normal text-amber-600">(solo administrador)</span></h4>
                        <p class="text-[#425b76]">
                            Configura endpoints externos para cargar clientes automáticamente. También permite importar desde archivo:
                        </p>
                        <ul class="list-inside list-disc space-y-1 text-[#425b76]">
                            <li><strong>Importar clientes:</strong> Descarga la plantilla en formato Excel (.xlsx) o CSV, complétala y súbela. Acepta archivos .xlsx, .xls, .csv.</li>
                            <li><strong>Endpoints de fuente:</strong> URLs que el sistema consulta para sincronizar clientes.</li>
                            <li><strong>API crear clientes:</strong> POST <code class="rounded bg-[#e3e8ee] px-1">/api/agents/{id}/clients</code> para crear o actualizar clientes desde N8N o sistemas externos (documentación en la pestaña Fuente).</li>
                        </ul>

                        <h4 class="font-medium text-[#33475b]">Detalle del cliente</h4>
                        <p class="text-[#425b76]">
                            Desde la lista de clientes (columna <strong>Alertas de llamada</strong>) o desde <strong>Reportes</strong>, al hacer clic en un cliente se abre su detalle con: avance del proceso, notas, historial de contacto, llamadas y transcripciones, y las <strong>alertas de llamada</strong> registradas (donde puedes reclasificar su categoría o eliminarlas).
                        </p>
                    </div>
                </section>

                <!-- Configuración General -->
                <section class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                    <div class="border-b border-[#e3e8ee] bg-[#f5f8fa] px-6 py-4">
                        <h3 class="text-lg font-semibold text-[#33475b]">Configuración (General)</h3>
                        <p class="mt-1 text-sm text-[#425b76]">
                            <strong>Dónde:</strong> Empresa → pestaña <strong>Configuración</strong>
                        </p>
                    </div>
                    <div class="space-y-4 p-6">
                        <p class="text-[#33475b]">
                            Información básica de la empresa: nombre, descripción, estado y prompt del sistema. Aquí también se generan las <strong>API Keys</strong> para consumir los endpoints de la empresa desde sistemas externos (N8N, integraciones). Las API Keys se usan en los headers <code class="rounded bg-[#e3e8ee] px-1">Authorization: Bearer</code> o <code class="rounded bg-[#e3e8ee] px-1">X-Api-Key</code>.
                        </p>
                    </div>
                </section>

                <!-- Integración -->
                <section class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                    <div class="border-b border-[#e3e8ee] bg-[#f5f8fa] px-6 py-4">
                        <h3 class="text-lg font-semibold text-[#33475b]">Integración</h3>
                        <p class="mt-1 text-sm text-[#425b76]">
                            <strong>Dónde:</strong> Empresa → grupo <strong>Integración</strong> (solo administrador)
                        </p>
                    </div>
                    <div class="space-y-6 p-6">
                        <h4 class="font-medium text-[#33475b]">Endpoints</h4>
                        <p class="text-[#425b76]">
                            Define URLs y métodos HTTP que la empresa puede invocar (webhooks hacia N8N u otros sistemas). Permite probar la conexión y ver logs de cada llamada.
                        </p>

                        <h4 class="font-medium text-[#33475b]">Recolección WhatsApp</h4>
                        <p class="text-[#425b76]">
                            Configura la URL y variables para recolectar datos estructurados durante las conversaciones de WhatsApp. El endpoint recibe los datos enviados por el usuario.
                        </p>
                    </div>
                </section>

                <!-- Comunicación -->
                <section id="comunicacion" class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                    <div class="border-b border-[#e3e8ee] bg-[#f5f8fa] px-6 py-4">
                        <h3 class="text-lg font-semibold text-[#33475b]">Comunicación <span class="text-xs font-normal text-amber-600">(solo administrador)</span></h3>
                        <p class="mt-1 text-sm text-[#425b76]">
                            <strong>Dónde:</strong> Empresa → grupo <strong>Comunicación</strong> (4 sub-pestañas: Mensajes, Llamadas, Callbacks, Campañas)
                        </p>
                    </div>
                    <div class="space-y-6 p-6">
                        <h4 class="font-medium text-[#33475b]">Mensajes</h4>
                        <p class="text-[#425b76]">
                            Configura el webhook y horarios para contactar por WhatsApp. Incluye:
                        </p>
                        <ul class="list-inside list-disc space-y-1 text-[#425b76]">
                            <li><strong>Webhook URL:</strong> URL a la que se envían los datos del cliente cuando se ejecuta un contacto.</li>
                            <li><strong>Plantillas (id_plantilla):</strong> Lista de plantillas con ID y nombre. El ID debe coincidir con tu sistema (ej. WhatsApp Business). Selecciona una como <strong>principal</strong> para contacto manual. En cada horario de campaña puedes asignar una plantilla específica para que el webhook reciba ese <code class="rounded bg-[#e3e8ee] px-1">id_plantilla</code> cuando el cron ejecute a esa hora.</li>
                            <li><strong>Variables dinámicas de plantilla:</strong> en cada plantilla puedes activar «Tiene variables dinámicas», nombrar cada variable (en orden) y mapearla a un valor del cliente (campo base, campo dinámico o la <strong>URL del formulario</strong> del cliente). Al enviar, las variables se resuelven por cliente y el webhook las recibe como <code class="rounded bg-[#e3e8ee] px-1">variables: [{ name, value }]</code>, en el mismo orden (útil para {{1}}, {{2}} de WhatsApp).</li>
                            <li><strong>Reglas de ejecución:</strong> Filtran qué clientes se contactan. Ej: "estado = activo". Opcional: "Contactar a los X días" desde fecha de carga, creación o un campo personalizado.</li>
                            <li><strong>Horarios de campaña:</strong> Define horas concretas (ej: 09:00, 14:00), días de la semana, días excluidos y asigna reglas a cada una. Cada horario puede tener su propia plantilla.</li>
                        </ul>

                        <h4 class="font-medium text-[#33475b]">Llamadas</h4>
                        <p class="text-[#425b76]">
                            Similar a Mensajes pero para el canal de llamadas. Webhook URL, horarios, reglas y slots de campaña.
                        </p>

                        <h4 class="font-medium text-[#33475b]">Callbacks</h4>
                        <p class="text-[#425b76]">
                            Cuando un usuario solicita que lo llamen en una fecha específica, programa aquí la tarea:
                        </p>
                        <ul class="list-inside list-disc space-y-1 text-[#425b76]">
                            <li><strong>Listado:</strong> Filtra por Pendiente, Completado o Cancelado.</li>
                            <li><strong>Programar callback:</strong> Selecciona cliente, fecha, hora (opcional), canal (llamada/WhatsApp) y notas.</li>
                            <li><strong>Ejecución:</strong> Un cron diario (08:00) procesa las solicitudes del día y crea colas de contacto que llaman al webhook.</li>
                            <li><strong>Registro en cliente:</strong> En el modal de WhatsApp de cada cliente se muestra el historial de solicitudes de callback.</li>
                            <li><strong>API:</strong> POST <code class="rounded bg-[#e3e8ee] px-1">/api/agents/{id}/callback-requests</code> para programar desde sistemas externos. Ver documentación en la pestaña Callbacks.</li>
                        </ul>

                        <h4 class="font-medium text-[#33475b]">Campañas</h4>
                        <p class="text-[#425b76]">
                            Envíos puntuales o programados a un grupo de clientes seleccionados por reglas. Permite lanzar «Ejecutar ahora» o programar según los horarios definidos, y ver el estado de las colas de contacto generadas.
                        </p>
                        <p class="mt-1 text-[#425b76]">
                            Al crear una campaña eliges el <strong>canal</strong> (WhatsApp o Llamada) y, para WhatsApp, <strong>qué plantilla se envía en cada horario</strong> (columna de plantilla junto a cada hora, tanto en la configuración de horarios como al crear la campaña «según horarios»). Si no eliges plantilla en un horario, se usa la principal por defecto.
                        </p>
                    </div>
                </section>

                <!-- Formulario -->
                <section id="formulario" class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                    <div class="border-b border-[#e3e8ee] bg-[#f5f8fa] px-6 py-4">
                        <h3 class="text-lg font-semibold text-[#33475b]">Formulario</h3>
                        <p class="mt-1 text-sm text-[#425b76]">
                            <strong>Dónde:</strong> Empresa → pestaña <strong>Formulario</strong>
                        </p>
                    </div>
                    <div class="space-y-4 p-6">
                        <p class="text-[#33475b]">
                            Define un formulario de preguntas que tus clientes responden desde un <strong>enlace público</strong> (sin necesidad de iniciar sesión). Útil para recolectar datos antes o después del contacto.
                        </p>
                        <ul class="list-inside list-disc space-y-1 text-[#425b76]">
                            <li><strong>Preguntas:</strong> agrega preguntas con su etiqueta, texto de ayuda y si son obligatorias.</li>
                            <li><strong>Enlaces por cliente:</strong> genera un enlace único por cliente. Cada respuesta queda asociada a ese cliente.</li>
                            <li><strong>Respuestas:</strong> consulta lo que respondió cada cliente y vuelve a generar el enlace si es necesario.</li>
                        </ul>
                    </div>
                </section>

                <!-- Reportes y Logs -->
                <section class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                    <div class="border-b border-[#e3e8ee] bg-[#f5f8fa] px-6 py-4">
                        <h3 class="text-lg font-semibold text-[#33475b]">Reportes y Logs</h3>
                        <p class="mt-1 text-sm text-[#425b76]">
                            <strong>Dónde:</strong> Empresa → pestañas <strong>Reportes</strong> y <strong>Logs</strong>
                        </p>
                    </div>
                    <div class="space-y-6 p-6">
                        <h4 class="font-medium text-[#33475b]">Reportes</h4>
                        <p class="text-[#425b76]">
                            Estadísticas por período: total de clientes, mensajes enviados por WhatsApp y llamadas realizadas. Filtra por rango de fechas.
                        </p>

                        <h4 class="font-medium text-[#33475b]">Logs <span class="text-xs font-normal text-amber-600">(solo administrador)</span></h4>
                        <p class="text-[#425b76]">
                            Historial de llamadas a endpoints, contactos con clientes y últimas colas de mensajes/llamadas ejecutadas por el cron.
                        </p>
                    </div>
                </section>

                <!-- Alertas de llamada -->
                <section id="alertas" class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                    <div class="border-b border-[#e3e8ee] bg-[#f5f8fa] px-6 py-4">
                        <h3 class="text-lg font-semibold text-[#33475b]">Alertas de llamada</h3>
                        <p class="mt-1 text-sm text-[#425b76]">
                            <strong>Dónde:</strong> Configuración global (categorías y correos) · Empresa → Clientes / Reportes (ver alertas por cliente)
                        </p>
                    </div>
                    <div class="space-y-4 p-6">
                        <p class="text-[#33475b]">
                            Las <strong>alertas de llamada</strong> registran incidencias o eventos ocurridos durante las llamadas del agente IA. Un mismo cliente puede tener <strong>varias</strong> alertas, y cada una guarda la fecha y hora de registro.
                        </p>
                        <ul class="list-inside list-disc space-y-1 text-[#425b76]">
                            <li><strong>Categorías:</strong> clasifican cada alerta (por defecto «Problema» y «Alerta»). Se gestionan en <strong>Configuración global → Categorías de alerta de llamada</strong>: puedes crear, editar (nombre y color) o eliminar las que necesites.</li>
                            <li><strong>Notificación por correo:</strong> en <strong>Configuración global → Correos de alerta de llamada</strong> defines uno o varios correos. Cada vez que llega una nueva alerta, esos correos reciben un aviso automático con la información, usando la plantilla de correo del sistema.</li>
                            <li><strong>Cómo se registran:</strong> el agente IA o un sistema externo envía la alerta al endpoint <code class="rounded bg-[#e3e8ee] px-1">POST /api/agents/{id}/call-alerts</code> con <code class="rounded bg-[#e3e8ee] px-1">phone</code>, <code class="rounded bg-[#e3e8ee] px-1">numero_llamada</code>, <code class="rounded bg-[#e3e8ee] px-1">paso_llamada</code>, <code class="rounded bg-[#e3e8ee] px-1">descripcion</code> y <code class="rounded bg-[#e3e8ee] px-1">categoria</code> (opcional). La alerta se vincula al cliente con ese teléfono; si no existe, se guarda igual sin vincular.</li>
                            <li><strong>Dónde verlas:</strong> en la lista de clientes de la empresa hay una columna <strong>Alertas de llamada</strong>; al hacer clic se abre una ventana con todas las alertas de ese cliente, donde puedes reclasificar su categoría o eliminarlas.</li>
                        </ul>
                    </div>
                </section>

                <!-- APIs disponibles -->
                <section id="apis" class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                    <div class="border-b border-[#e3e8ee] bg-[#f5f8fa] px-6 py-4">
                        <h3 class="text-lg font-semibold text-[#33475b]">APIs disponibles</h3>
                        <p class="mt-1 text-sm text-[#425b76]">
                            Todas requieren API key de la empresa (Bearer o X-Api-Key)
                        </p>
                    </div>
                    <div class="overflow-x-auto p-6">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-[#e3e8ee]">
                                    <th class="px-3 py-2 text-left font-medium text-[#33475b]">Método</th>
                                    <th class="px-3 py-2 text-left font-medium text-[#33475b]">Ruta</th>
                                    <th class="px-3 py-2 text-left font-medium text-[#33475b]">Descripción</th>
                                </tr>
                            </thead>
                            <tbody class="text-[#425b76]">
                                <tr class="border-b border-[#e3e8ee]">
                                    <td class="px-3 py-2 font-mono">POST</td>
                                    <td class="px-3 py-2 font-mono">/api/agents/{id}/clients</td>
                                    <td>Crea o actualiza cliente (por document o phone)</td>
                                </tr>
                                <tr class="border-b border-[#e3e8ee]">
                                    <td class="px-3 py-2 font-mono">POST</td>
                                    <td class="px-3 py-2 font-mono">/api/agents/{id}/collect-data</td>
                                    <td>Recibe datos recolectados desde WhatsApp</td>
                                </tr>
                                <tr class="border-b border-[#e3e8ee]">
                                    <td class="px-3 py-2 font-mono">GET / POST</td>
                                    <td class="px-3 py-2 font-mono">/api/agents/{id}/clients/by-phone</td>
                                    <td>Consulta un cliente completo por parámetro <code class="rounded bg-[#f5f8fa] px-1">phone</code> (query o body)</td>
                                </tr>
                                <tr class="border-b border-[#e3e8ee]">
                                    <td class="px-3 py-2 font-mono">POST</td>
                                    <td class="px-3 py-2 font-mono">/api/agents/{id}/callback-requests</td>
                                    <td>Programa un callback (llamada/mensaje) para una fecha</td>
                                </tr>
                                <tr class="border-b border-[#e3e8ee]">
                                    <td class="px-3 py-2 font-mono">POST</td>
                                    <td class="px-3 py-2 font-mono">/api/agents/{id}/call-alerts</td>
                                    <td>Registra una alerta de llamada (<code class="rounded bg-[#f5f8fa] px-1">phone</code>, <code class="rounded bg-[#f5f8fa] px-1">numero_llamada</code>, <code class="rounded bg-[#f5f8fa] px-1">paso_llamada</code>, <code class="rounded bg-[#f5f8fa] px-1">descripcion</code>, <code class="rounded bg-[#f5f8fa] px-1">categoria</code> opcional). Notifica por correo</td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="mt-4 text-sm text-[#425b76]">
                            La documentación detallada de cada endpoint (body, respuesta, ejemplos cURL) está en: <strong>Empresa → Clientes → Fuente</strong> (API crear/actualizar clientes), <strong>Empresa → Callbacks</strong> (API callbacks).
                        </p>
                    </div>
                </section>

                <!-- Configuración global -->
                <section class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                    <div class="border-b border-[#e3e8ee] bg-[#f5f8fa] px-6 py-4">
                        <h3 class="text-lg font-semibold text-[#33475b]">Configuración global</h3>
                        <p class="mt-1 text-sm text-[#425b76]">
                            <strong>Dónde:</strong> Menú → Configuración (solo administrador)
                        </p>
                    </div>
                    <div class="space-y-4 p-6">
                        <p class="text-[#33475b]">
                            Ajustes que aplican a toda la plataforma:
                        </p>
                        <ul class="list-inside list-disc space-y-1 text-[#425b76]">
                            <li><strong>Marca:</strong> nombre de la empresa, logo, favicon y color principal.</li>
                            <li><strong>Contacto y costos:</strong> información de contacto, costos por mensaje/llamada, método de pago, tope mensual de mensajes e historial de transacciones.</li>
                            <li><strong>Categorías de alerta de llamada:</strong> crea, edita o elimina las clasificaciones de las alertas (ver <a href="#alertas" class="text-[var(--color-primary)] underline">Alertas de llamada</a>).</li>
                            <li><strong>Correos de alerta de llamada:</strong> uno o varios correos que reciben aviso al llegar una nueva alerta.</li>
                            <li><strong>Orígenes de datos:</strong> de dónde se leen las conversaciones de WhatsApp y las transcripciones de llamadas (base interna o Supabase).</li>
                        </ul>
                    </div>
                </section>

                <!-- Accesos rápidos -->
                <section class="rounded-lg border border-[#e3e8ee] bg-white p-6">
                    <h3 class="text-lg font-semibold text-[#33475b]">Accesos rápidos</h3>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <Link
                            :href="route('dashboard')"
                            class="rounded-md bg-[var(--color-primary-light)] px-4 py-2 text-sm font-medium text-[var(--color-primary)] transition hover:bg-[var(--color-primary)] hover:text-[var(--color-primary-foreground)]"
                        >
                            Ir al Dashboard
                        </Link>
                        <Link
                            :href="route('agents.index')"
                            class="rounded-md bg-[var(--color-primary-light)] px-4 py-2 text-sm font-medium text-[var(--color-primary)] transition hover:bg-[var(--color-primary)] hover:text-[var(--color-primary-foreground)]"
                        >
                            Ver Empresas
                        </Link>
                        <Link
                            v-if="$page.props.auth.canAccessSettings"
                            :href="route('settings.index')"
                            class="rounded-md bg-[var(--color-primary-light)] px-4 py-2 text-sm font-medium text-[var(--color-primary)] transition hover:bg-[var(--color-primary)] hover:text-[var(--color-primary-foreground)]"
                        >
                            Configuración
                        </Link>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
