{*
 * Template principal del módulo Informes Contables CON VISTA EN PANTALLA
 * Ubicación: /modules/informescontables/views/templates/admin/informes.tpl
 *}

<div class="panel">
    <h3><i class="icon-bar-chart"></i> {l s='Informes Contables' mod='informescontables'}</h3>
    <div class="row">
        <div class="col-lg-2">
            <div class="list-group">
                <a href="#" class="list-group-item active" data-tab="listado">
                    <i class="icon-list"></i> {l s='Listado Personalizado' mod='informescontables'}
                </a>
                <a href="#" class="list-group-item" data-tab="modelo347">
                    <i class="icon-file-text"></i> {l s='Modelo 347' mod='informescontables'}
                </a>
            </div>
        </div>
        <div class="col-lg-10">
            <!-- Tab Listado Personalizado -->
            <div id="tab-listado" class="tab-content">
                <div class="panel">
                    <h4>{l s='Generar Listado Personalizado' mod='informescontables'}</h4>
                    <form id="form-listado" class="form-horizontal">
                        <div class="form-group">
                            <label class="control-label col-lg-2">{l s='Fecha inicio' mod='informescontables'}</label>
                            <div class="col-lg-3">
                                <div class="input-group">
                                    <input type="text" name="fecha_inicio" id="fecha_inicio" class="datepicker form-control" />
                                    <span class="input-group-addon"><i class="icon-calendar"></i></span>
                                </div>
                            </div>
                            <label class="control-label col-lg-2">{l s='Fecha fin' mod='informescontables'}</label>
                            <div class="col-lg-3">
                                <div class="input-group">
                                    <input type="text" name="fecha_fin" id="fecha_fin" class="datepicker form-control" />
                                    <span class="input-group-addon"><i class="icon-calendar"></i></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="control-label col-lg-2">{l s='Campos a mostrar' mod='informescontables'}</label>
                            <div class="col-lg-10">
                                <div class="row">
                                    {foreach from=$campos_disponibles key=table item=fields}
                                        <div class="col-lg-4">
                                            <h5>{if $table == 'order'}{l s='Pedido' mod='informescontables'}
                                                {elseif $table == 'customer'}{l s='Cliente' mod='informescontables'}
                                                {elseif $table == 'address'}{l s='Dirección' mod='informescontables'}
                                                {elseif $table == 'tax'}{l s='Impuestos' mod='informescontables'}{/if}
                                            </h5>
                                            {foreach from=$fields key=field item=label}
                                                <div class="checkbox">
                                                    <label>
                                                        <input type="checkbox" name="campos[{$table}][]" value="{$field}" 
                                                               {if $table == 'address' && ($field == 'company' || $field == 'vat_number')}checked{/if} />
                                                        {$label}
                                                    </label>
                                                </div>
                                            {/foreach}
                                        </div>
                                    {/foreach}
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="control-label col-lg-2">{l s='Filtros' mod='informescontables'}</label>
                            <div class="col-lg-10">
                                <div class="row">
                                    <div class="col-lg-4">
                                        <label>{l s='Forma de pago' mod='informescontables'}</label>
                                        <select name="filtros[payment][]" class="form-control chosen" multiple>
                                            {foreach from=$payments item=payment}
                                                <option value="{$payment.id}">{$payment.name}</option>
                                            {/foreach}
                                        </select>
                                    </div>
                                    <div class="col-lg-4">
                                        <label>{l s='Transportista' mod='informescontables'}</label>
                                        <select name="filtros[carrier][]" class="form-control chosen" multiple>
                                            {foreach from=$carriers item=carrier}
                                                <option value="{$carrier.id_carrier}">{$carrier.name}</option>
                                            {/foreach}
                                        </select>
                                    </div>
                                    <div class="col-lg-4">
                                        <label>{l s='Estado del pedido' mod='informescontables'}</label>
                                        <select name="filtros[state][]" class="form-control chosen" multiple>
                                            {foreach from=$order_states item=state}
                                                <option value="{$state.id_order_state}">{$state.name}</option>
                                            {/foreach}
                                        </select>
                                    </div>
                                </div>
                                <div class="row" style="margin-top: 15px;">
                                    <div class="col-lg-4">
                                        <div class="checkbox">
                                            <label>
                                                <input type="checkbox" name="filtros[solo_empresas]" value="1" />
                                                {l s='Solo empresas (con CIF y nombre empresa)' mod='informescontables'}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="control-label col-lg-2">{l s='Formato' mod='informescontables'}</label>
                            <div class="col-lg-4">
                                <select name="formato" id="formato-listado" class="form-control">
                                    <option value="pantalla">Ver en pantalla</option>
                                    <option value="excel">Descargar Excel</option>
                                    <option value="pdf">Descargar PDF</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="col-lg-offset-2 col-lg-10">
                                <button type="submit" class="btn btn-primary">
                                    <i class="icon-search"></i> <span id="btn-text-listado">Ver Listado</span>
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <!-- NUEVO: Resultados del listado en pantalla -->
                    <div id="resultados-listado" style="display:none;">
                        <hr />
                        <h4>Resultados del Listado</h4>
                        
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="alert alert-info">
                                    <strong>Total de registros:</strong> <span id="total-registros-listado">0</span>
                                </div>
                            </div>
                            <div class="col-lg-6 text-right">
                                <button type="button" class="btn btn-success" id="btn-exportar-listado-excel">
                                    <i class="icon-download"></i> Exportar a Excel
                                </button>
                                <button type="button" class="btn btn-info" id="btn-exportar-listado-pdf">
                                    <i class="icon-download"></i> Exportar a PDF
                                </button>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered" id="tabla-listado">
                                <thead id="thead-listado"></thead>
                                <tbody id="tbody-listado"></tbody>
                            </table>
                        </div>
                        
                        <!-- Paginación -->
                        <div class="row">
                            <div class="col-lg-6">
                                <select id="registros-por-pagina" class="form-control" style="width: 200px;">
                                    <option value="25">25 por página</option>
                                    <option value="50" selected>50 por página</option>
                                    <option value="100">100 por página</option>
                                    <option value="all">Todos</option>
                                </select>
                            </div>
                            <div class="col-lg-6">
                                <nav>
                                    <ul class="pagination pull-right" id="paginacion-listado"></ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tab Modelo 347 -->
            <div id="tab-modelo347" class="tab-content" style="display:none;">
                <div class="panel">
                    <h4>{l s='Generar Modelo 347' mod='informescontables'}</h4>
                    <form id="form-347" class="form-horizontal">
                        <div class="form-group">
                            <label class="control-label col-lg-2">{l s='Año' mod='informescontables'}</label>
                            <div class="col-lg-3">
                                <select name="year" class="form-control">
                                    {for $year={date('Y')} to 2015 step -1}
                                        <option value="{$year}" {if $year == {date('Y')-1}}selected{/if}>{$year}</option>
                                    {/for}
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="control-label col-lg-2">{l s='Importe mínimo' mod='informescontables'}</label>
                            <div class="col-lg-3">
                                <div class="input-group">
                                    <input type="text" name="importe_minimo" value="{$importe_347}" class="form-control" />
                                    <span class="input-group-addon">€</span>
                                </div>
                                <p class="help-block">{l s='Solo se incluirán clientes con operaciones superiores a este importe' mod='informescontables'}</p>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="col-lg-offset-2 col-lg-10">
                                <button type="submit" class="btn btn-primary">
                                    <i class="icon-search"></i> {l s='Buscar Clientes' mod='informescontables'}
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Resultados 347 -->
                    <div id="resultados-347" style="display:none;">
                        <hr />
                        <h4>{l s='Resultados Modelo 347' mod='informescontables'}</h4>
                        
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="alert alert-info">
                                    <strong>{l s='Resumen:' mod='informescontables'}</strong>
                                    <span id="resumen-347"></span>
                                </div>
                            </div>
                        </div>
                        
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#empresas" data-toggle="tab">{l s='Empresas' mod='informescontables'} <span class="badge" id="count-empresas">0</span></a></li>
                            <li><a href="#particulares" data-toggle="tab">{l s='Particulares' mod='informescontables'} <span class="badge" id="count-particulares">0</span></a></li>
                        </ul>
                        
                        <div class="tab-content">
                            <div class="tab-pane active" id="empresas">
                                <table class="table table-striped" id="tabla-empresas">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="check-all-empresas" /></th>
                                            <th>{l s='CIF' mod='informescontables'}</th>
                                            <th>{l s='Empresa' mod='informescontables'}</th>
                                            <th>{l s='Dirección' mod='informescontables'}</th>
                                            <th>{l s='Email' mod='informescontables'}</th>
                                            <th>{l s='Total Anual' mod='informescontables'}</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <div class="tab-pane" id="particulares">
                                <table class="table table-striped" id="tabla-particulares">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="check-all-particulares" /></th>
                                            <th>{l s='NIF' mod='informescontables'}</th>
                                            <th>{l s='Nombre' mod='informescontables'}</th>
                                            <th>{l s='Dirección' mod='informescontables'}</th>
                                            <th>{l s='Email' mod='informescontables'}</th>
                                            <th>{l s='Total Anual' mod='informescontables'}</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="row" style="margin-top: 20px;">
                            <div class="col-lg-6">
                                <h5>{l s='Acciones' mod='informescontables'}</h5>
                                <button type="button" class="btn btn-success" id="btn-exportar-347">
                                    <i class="icon-download"></i> {l s='Exportar' mod='informescontables'}
                                </button>
                                <select id="formato-exportar-347" class="form-control" style="width: 150px; display: inline-block;">
                                    <option value="excel">Excel</option>
                                    <option value="pdf">PDF</option>
                                </select>
                            </div>
                            <div class="col-lg-6">
                                <h5>{l s='Enviar por Email' mod='informescontables'}</h5>
                                <button type="button" class="btn btn-info" id="btn-email-347" data-toggle="modal" data-target="#modal-email">
                                    <i class="icon-envelope"></i> {l s='Enviar a clientes seleccionados' mod='informescontables'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Email -->
<div class="modal fade" id="modal-email" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title">{l s='Enviar Modelo 347 por Email' mod='informescontables'}</h4>
            </div>
            <div class="modal-body">
                <form id="form-email-347">
                    <div class="form-group">
                        <label>{l s='Asunto' mod='informescontables'}</label>
                        <input type="text" name="asunto" class="form-control" value="{l s='Información Modelo 347 - Año {year}' mod='informescontables'}" />
                    </div>
                    <div class="form-group">
                        <label>{l s='Mensaje' mod='informescontables'}</label>
                        <textarea name="mensaje" rows="10" class="form-control">{l s='Estimado/a {nombre}:

Le informamos que durante el año {year} las operaciones realizadas con nuestra empresa han superado el importe de 3.005,06€, por lo que serán incluidas en la declaración del Modelo 347.

El importe total de las operaciones realizadas asciende a: {total_anual}

Detalle de facturas emitidas:
{facturas}

Si detecta algún error en estos datos, le rogamos nos lo comunique a la mayor brevedad posible.

Atentamente,
[Su empresa]' mod='informescontables'}</textarea>
                        <p class="help-block">{l s='Variables disponibles: {nombre}, {empresa}, {total_anual}, {year}, {facturas}' mod='informescontables'}</p>
                    </div>
                    <div class="form-group">
                        <label>{l s='Incluir en el email' mod='informescontables'}</label>
                        <div class="radio">
                            <label>
                                <input type="radio" name="incluir_solo_cliente" value="true" checked />
                                {l s='Solo los datos del cliente' mod='informescontables'}
                            </label>
                        </div>
                        <div class="radio">
                            <label>
                                <input type="radio" name="incluir_solo_cliente" value="false" />
                                {l s='Informe completo (todos los clientes)' mod='informescontables'}
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{l s='Cancelar' mod='informescontables'}</button>
                <button type="button" class="btn btn-primary" id="btn-enviar-emails">
                    <i class="icon-send"></i> {l s='Enviar Emails' mod='informescontables'}
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Estilos CSS incluidos en el template -->
<style>
    .tab-content .panel { margin-top: 0; }
    .list-group-item { cursor: pointer; transition: all 0.3s ease; }
    .list-group-item:hover { background-color: #f5f5f5; }
    .list-group-item.active { background-color: #00aff0; border-color: #00aff0; color: #fff; }
    .list-group-item.active:hover { background-color: #0099d4; border-color: #0099d4; }
    .checkbox label { font-weight: normal; cursor: pointer; }
    .checkbox label:hover { color: #00aff0; }
    .chosen-container { width: 100% !important; }
    #resultados-347 table, #tabla-listado { margin-top: 15px; }
    #resultados-347 table th, #tabla-listado th { background-color: #f5f5f5; font-weight: 600; }
    #resultados-347 table td, #tabla-listado td { vertical-align: middle; }
    .modal-body .help-block { font-size: 12px; color: #737373; }
    .table-responsive { max-height: 500px; overflow-y: auto; }
    .pagination { margin: 10px 0; }
    .pagination li a { cursor: pointer; }
    .alert-info { background-color: #d9edf7; border-color: #bce8f1; color: #31708f; }
    
    /* Estilos para la tabla del listado */
    #tabla-listado th { 
        position: sticky; 
        top: 0; 
        background-color: #f5f5f5; 
        z-index: 10; 
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .form-horizontal .control-label { text-align: left; margin-bottom: 5px; }
        .list-group { margin-bottom: 20px; }
        .table-responsive { font-size: 12px; }
    }
</style>

<script>
// JavaScript se carga desde el archivo back.js
var ajax_url = '{$ajax_url}';

// Variables globales para paginación
var datosListadoCompletos = [];
var datosListadoActuales = [];
var paginaActual = 1;
var registrosPorPagina = 50;

// Cambiar texto del botón según formato seleccionado
$(document).ready(function() {
    $('#formato-listado').change(function() {
        var formato = $(this).val();
        var btnText = $('#btn-text-listado');
        var btnIcon = btnText.prev('i');
        
        if (formato === 'pantalla') {
            btnIcon.removeClass('icon-download').addClass('icon-search');
            btnText.text('Ver Listado');
        } else {
            btnIcon.removeClass('icon-search').addClass('icon-download');
            btnText.text('Generar ' + (formato === 'excel' ? 'Excel' : 'PDF'));
        }
    });
});
</script>