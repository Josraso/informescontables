/**
 * JavaScript para el módulo Informes Contables CON VISTA EN PANTALLA
 * Ubicación: /modules/informescontables/views/js/back.js
 */

$(document).ready(function() {
    // Inicializar componentes
    $('.datepicker').datepicker({
        dateFormat: 'yy-mm-dd'
    });
    
    $('.chosen').chosen({
        width: '100%',
        placeholder_text_multiple: 'Seleccione opciones...'
    });
    
    // Navegación entre tabs
    $('.list-group-item').click(function(e) {
        e.preventDefault();
        $('.list-group-item').removeClass('active');
        $(this).addClass('active');
        
        var tab = $(this).data('tab');
        $('.tab-content').hide();
        $('#tab-' + tab).show();
    });
    
    // Cambiar texto del botón según formato
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
    
    // Formulario Listado Personalizado MEJORADO
    $('#form-listado').submit(function(e) {
        e.preventDefault();
        
        // Validar que hay campos seleccionados
        if ($('input[name^="campos"]:checked').length === 0) {
            showErrorMessage('Debe seleccionar al menos un campo para mostrar');
            return;
        }
        
        // Validar fechas
        var fecha_inicio = $('#fecha_inicio').val();
        var fecha_fin = $('#fecha_fin').val();
        
        if (!fecha_inicio || !fecha_fin) {
            showErrorMessage('Debe seleccionar el rango de fechas');
            return;
        }
        
        if (fecha_inicio > fecha_fin) {
            showErrorMessage('La fecha de inicio no puede ser mayor que la fecha fin');
            return;
        }
        
        var formato = $('#formato-listado').val();
        
        // Preparar datos
        var formData = $(this).serializeArray();
        formData.push({name: 'ajax', value: '1'});
        formData.push({name: 'action', value: 'GenerarListado'});
        
        // Mostrar loading
        if (formato === 'pantalla') {
            showLoadingMessage('Cargando datos...');
        } else {
            showLoadingMessage('Generando archivo...');
        }
        
        $.ajax({
            url: ajax_url,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                hideLoadingMessage();
                
                if (response.error) {
                    showErrorMessage(response.error);
                } else if (response.success) {
                    if (formato === 'pantalla') {
                        // Mostrar en pantalla
                        mostrarResultadosListado(response);
                        showSuccessMessage('Se encontraron ' + response.count + ' registros.');
                    } else {
                        // Descargar archivo
                        showSuccessMessage('Archivo generado correctamente. Se encontraron ' + response.count + ' registros.');
                        window.location.href = response.file;
                    }
                }
            },
            error: function() {
                hideLoadingMessage();
                showErrorMessage('Error al procesar la solicitud');
            }
        });
    });
    
    // NUEVA FUNCIÓN: Mostrar resultados del listado en pantalla
    function mostrarResultadosListado(response) {
        datosListadoCompletos = response.data;
        var campos = response.campos;
        var campos_disponibles = response.campos_disponibles;
        
        // Mostrar sección de resultados
        $('#resultados-listado').show();
        $('#total-registros-listado').text(response.count);
        
        // Construir cabeceras de la tabla
        var thead = '';
        for (var table in campos) {
            for (var i = 0; i < campos[table].length; i++) {
                var field = campos[table][i];
                var label = campos_disponibles[table][field];
                thead += '<th>' + label + '</th>';
            }
        }
        thead += '<th>Es Empresa</th>';
        $('#thead-listado').html('<tr>' + thead + '</tr>');
        
        // Guardar estructura de campos para exportación
        window.camposListado = campos;
        
        // Inicializar paginación
        paginaActual = 1;
        registrosPorPagina = parseInt($('#registros-por-pagina').val());
        actualizarTablaListado();
        actualizarPaginacionListado();
    }
    
    // NUEVA FUNCIÓN: Actualizar tabla del listado
    function actualizarTablaListado() {
        var inicio = (paginaActual - 1) * registrosPorPagina;
        var fin = registrosPorPagina === 'all' ? datosListadoCompletos.length : inicio + registrosPorPagina;
        
        datosListadoActuales = datosListadoCompletos.slice(inicio, fin);
        
        var tbody = '';
        for (var i = 0; i < datosListadoActuales.length; i++) {
            var item = datosListadoActuales[i];
            tbody += '<tr>';
            
            // Recorrer campos seleccionados
            for (var table in window.camposListado) {
                for (var j = 0; j < window.camposListado[table].length; j++) {
                    var field = window.camposListado[table][j];
                    var value = item[field] || '';
                    
                    // Formatear según tipo de campo
                    if (field.indexOf('total_') !== -1 || field.indexOf('tax_') !== -1) {
                        value = formatPrice(value) + '€';
                    } else if (field.indexOf('date') !== -1 && value) {
                        value = formatDate(value);
                    }
                    
                    tbody += '<td>' + value + '</td>';
                }
            }
            
            // Es empresa
            var esEmpresa = (!item.address_company || !item.vat_number) ? 'No' : 'Sí';
            tbody += '<td class="text-center">' + esEmpresa + '</td>';
            tbody += '</tr>';
        }
        
        $('#tbody-listado').html(tbody);
    }
    
    // NUEVA FUNCIÓN: Actualizar paginación del listado
    function actualizarPaginacionListado() {
        if (registrosPorPagina === 'all') {
            $('#paginacion-listado').hide();
            return;
        }
        
        var totalPaginas = Math.ceil(datosListadoCompletos.length / registrosPorPagina);
        var paginacion = '';
        
        // Botón anterior
        if (paginaActual > 1) {
            paginacion += '<li><a data-page="' + (paginaActual - 1) + '">&laquo;</a></li>';
        }
        
        // Números de página
        var inicio = Math.max(1, paginaActual - 2);
        var fin = Math.min(totalPaginas, paginaActual + 2);
        
        for (var i = inicio; i <= fin; i++) {
            var active = i === paginaActual ? ' class="active"' : '';
            paginacion += '<li' + active + '><a data-page="' + i + '">' + i + '</a></li>';
        }
        
        // Botón siguiente
        if (paginaActual < totalPaginas) {
            paginacion += '<li><a data-page="' + (paginaActual + 1) + '">&raquo;</a></li>';
        }
        
        $('#paginacion-listado').html(paginacion).show();
    }
    
    // Event handlers para paginación
    $(document).on('click', '#paginacion-listado a', function(e) {
        e.preventDefault();
        paginaActual = parseInt($(this).data('page'));
        actualizarTablaListado();
        actualizarPaginacionListado();
    });
    
    $('#registros-por-pagina').change(function() {
        registrosPorPagina = $(this).val() === 'all' ? 'all' : parseInt($(this).val());
        paginaActual = 1;
        actualizarTablaListado();
        actualizarPaginacionListado();
    });
    
    // NUEVOS BOTONES: Exportar desde vista en pantalla
    $('#btn-exportar-listado-excel').click(function() {
        exportarListadoDesdeVista('excel');
    });
    
    $('#btn-exportar-listado-pdf').click(function() {
        exportarListadoDesdeVista('pdf');
    });
    
    function exportarListadoDesdeVista(formato) {
        if (!datosListadoCompletos.length) {
            showErrorMessage('No hay datos para exportar');
            return;
        }
        
        showLoadingMessage('Generando archivo...');
        
        // Preparar datos para exportación
        var formData = $('#form-listado').serializeArray();
        formData.push({name: 'ajax', value: '1'});
        formData.push({name: 'action', value: 'GenerarListado'});
        formData.push({name: 'formato', value: formato});
        
        $.ajax({
            url: ajax_url,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                hideLoadingMessage();
                
                if (response.error) {
                    showErrorMessage(response.error);
                } else if (response.success) {
                    showSuccessMessage('Archivo generado correctamente');
                    window.location.href = response.file;
                }
            },
            error: function() {
                hideLoadingMessage();
                showErrorMessage('Error al generar el archivo');
            }
        });
    }
    
    // Formulario Modelo 347 (sin cambios significativos)
    $('#form-347').submit(function(e) {
        e.preventDefault();
        
        var year = $('select[name="year"]').val();
        var importe_minimo = $('input[name="importe_minimo"]').val();
        
        if (!importe_minimo || parseFloat(importe_minimo) <= 0) {
            showErrorMessage('El importe mínimo debe ser mayor que 0');
            return;
        }
        
        showLoadingMessage('Buscando clientes...');
        
        $.ajax({
            url: ajax_url,
            type: 'POST',
            data: {
                ajax: 1,
                action: 'Generar347',
                year: year,
                importe_minimo: importe_minimo
            },
            dataType: 'json',
            success: function(response) {
                hideLoadingMessage();
                
                if (response.error) {
                    showErrorMessage(response.error);
                } else if (response.success) {
                    mostrarResultados347(response, year);
                }
            },
            error: function() {
                hideLoadingMessage();
                showErrorMessage('Error al buscar clientes');
            }
        });
    });
    
    // Mostrar resultados 347
    function mostrarResultados347(data, year) {
        $('#resultados-347').show();
        
        // Actualizar resumen
        var total_empresas = data.empresas.length;
        var total_particulares = data.particulares.length;
        $('#resumen-347').text('Se encontraron ' + total_empresas + ' empresas y ' + total_particulares + ' particulares. Total: ' + data.total + ' registros.');
        
        // Actualizar contadores
        $('#count-empresas').text(total_empresas);
        $('#count-particulares').text(total_particulares);
        
        // Limpiar tablas
        $('#tabla-empresas tbody').empty();
        $('#tabla-particulares tbody').empty();
        
        // Llenar tabla empresas
        $.each(data.empresas, function(i, empresa) {
            var row = '<tr data-id="' + empresa.id_customer + '">' +
                '<td><input type="checkbox" class="check-empresa" value="' + empresa.id_customer + '" /></td>' +
                '<td>' + empresa.vat_number + '</td>' +
                '<td>' + (empresa.company || empresa.address_company) + '</td>' +
                '<td>' + empresa.address1 + ', ' + empresa.postcode + ' ' + empresa.city + '</td>' +
                '<td>' + empresa.email + '</td>' +
                '<td class="text-right">' + formatPrice(empresa.total_anual) + '€</td>' +
                '</tr>';
            $('#tabla-empresas tbody').append(row);
        });
        
        // Llenar tabla particulares
        $.each(data.particulares, function(i, particular) {
            var row = '<tr data-id="' + particular.id_customer + '">' +
                '<td><input type="checkbox" class="check-particular" value="' + particular.id_customer + '" /></td>' +
                '<td>' + (particular.vat_number || 'N/D') + '</td>' +
                '<td>' + particular.firstname + ' ' + particular.lastname + '</td>' +
                '<td>' + particular.address1 + ', ' + particular.postcode + ' ' + particular.city + '</td>' +
                '<td>' + particular.email + '</td>' +
                '<td class="text-right">' + formatPrice(particular.total_anual) + '€</td>' +
                '</tr>';
            $('#tabla-particulares tbody').append(row);
        });
        
        // Guardar datos para exportar
        window.datos347 = {
            year: year,
            empresas: data.empresas,
            particulares: data.particulares
        };
    }
    
    // Check all empresas
    $('#check-all-empresas').change(function() {
        $('.check-empresa').prop('checked', $(this).prop('checked'));
    });
    
    // Check all particulares
    $('#check-all-particulares').change(function() {
        $('.check-particular').prop('checked', $(this).prop('checked'));
    });
    
    // Exportar 347
    $('#btn-exportar-347').click(function() {
        if (!window.datos347) {
            showErrorMessage('No hay datos para exportar');
            return;
        }
        
        var formato = $('#formato-exportar-347').val();
        
        showLoadingMessage('Generando archivo...');
        
        $.ajax({
            url: ajax_url,
            type: 'POST',
            data: {
                ajax: 1,
                action: 'Exportar347',
                data: JSON.stringify(window.datos347),
                formato: formato
            },
            dataType: 'json',
            success: function(response) {
                hideLoadingMessage();
                
                if (response.error) {
                    showErrorMessage(response.error);
                } else if (response.success) {
                    showSuccessMessage('Archivo generado correctamente');
                    window.location.href = response.file;
                }
            },
            error: function() {
                hideLoadingMessage();
                showErrorMessage('Error al generar el archivo');
            }
        });
    });
    
    // Enviar emails 347
    $('#btn-email-347').click(function() {
        var seleccionados = [];
        
        // Obtener clientes seleccionados
        $('.check-empresa:checked, .check-particular:checked').each(function() {
            var id = $(this).val();
            var cliente = null;
            
            // Buscar en empresas
            $.each(window.datos347.empresas, function(i, empresa) {
                if (empresa.id_customer == id) {
                    cliente = empresa;
                    return false;
                }
            });
            
            // Si no está en empresas, buscar en particulares
            if (!cliente) {
                $.each(window.datos347.particulares, function(i, particular) {
                    if (particular.id_customer == id) {
                        cliente = particular;
                        return false;
                    }
                });
            }
            
            if (cliente) {
                seleccionados.push(cliente);
            }
        });
        
        if (seleccionados.length === 0) {
            showErrorMessage('Debe seleccionar al menos un cliente');
            return;
        }
        
        // Guardar seleccionados para el modal
        window.clientesSeleccionados = seleccionados;
    });
    
    $('#btn-enviar-emails').click(function() {
        if (!window.clientesSeleccionados || window.clientesSeleccionados.length === 0) {
            showErrorMessage('No hay clientes seleccionados');
            $('#modal-email').modal('hide');
            return;
        }
        
        // Preparar y enviar emails
        var asunto = $('input[name="asunto"]').val();
        var mensaje = $('textarea[name="mensaje"]').val();
        var incluir_solo_cliente = $('input[name="incluir_solo_cliente"]:checked').val();
        
        // Reemplazar {year} en el asunto
        asunto = asunto.replace('{year}', window.datos347.year);
        
        $('#modal-email').modal('hide');
        showLoadingMessage('Enviando emails...');
        
        $.ajax({
            url: ajax_url,
            type: 'POST',
            data: {
                ajax: 1,
                action: 'EnviarEmails347',
                clientes: JSON.stringify(window.clientesSeleccionados),
                asunto: asunto,
                mensaje: mensaje,
                incluir_solo_cliente: incluir_solo_cliente
            },
            dataType: 'json',
            success: function(response) {
                hideLoadingMessage();
                
                if (response.error) {
                    showErrorMessage(response.error);
                } else if (response.success) {
                    var mensaje = 'Emails enviados correctamente. ';
                    mensaje += 'Enviados: ' + response.enviados + ', ';
                    mensaje += 'Errores: ' + response.errores;
                    showSuccessMessage(mensaje);
                }
            },
            error: function() {
                hideLoadingMessage();
                showErrorMessage('Error al enviar los emails');
            }
        });
    });
    
    // Funciones auxiliares
    function formatPrice(price) {
        return parseFloat(price).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,').replace('.', ',');
    }
    
    function formatDate(dateString) {
        var date = new Date(dateString);
        var day = String(date.getDate()).padStart(2, '0');
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var year = date.getFullYear();
        return day + '/' + month + '/' + year;
    }
    
    function showLoadingMessage(message) {
        if (typeof $.growl !== 'undefined') {
            $.growl.notice({
                title: '',
                message: message + ' <i class="icon-spinner icon-spin"></i>',
                duration: 60000
            });
        } else {
            // Fallback si no hay growl
            console.log(message);
        }
    }
    
    function hideLoadingMessage() {
        if (typeof $.growl !== 'undefined') {
            $('.growl-notice').remove();
        }
    }
    
    function showSuccessMessage(message) {
        if (typeof $.growl !== 'undefined') {
            $.growl.notice({
                title: '',
                message: message
            });
        } else {
            alert(message);
        }
    }
    
    function showErrorMessage(message) {
        if (typeof $.growl !== 'undefined') {
            $.growl.error({
                title: '',
                message: message
            });
        } else {
            alert('Error: ' + message);
        }
    }
});