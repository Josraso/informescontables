<?php
/**
 * Controlador principal del módulo Informes Contables
 * Gestiona la interfaz del backoffice CON VISTA EN PANTALLA
 */

class AdminInformesContablesController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->context = Context::getContext();
        
        parent::__construct();
        
        $this->meta_title = $this->l('Informes Contables');
    }

    public function initContent()
    {
        parent::initContent();
        
        $this->addJS($this->module->getPathUri().'views/js/back.js');
        $this->addCSS($this->module->getPathUri().'views/css/back.css');
        
        // Obtener todos los campos disponibles para el listado
        $campos_disponibles = $this->getCamposDisponibles();
        
        // Obtener formas de pago
        $payments = array();
        foreach (PaymentModule::getInstalledPaymentModules() as $payment) {
            $payments[] = array(
                'id' => $payment['name'],
                'name' => $payment['name']
            );
        }
        
        // Obtener transportistas
        $carriers = Carrier::getCarriers($this->context->language->id, true);
        
        // Obtener estados de pedido
        $order_states = OrderState::getOrderStates($this->context->language->id);
        
        $this->context->smarty->assign(array(
            'campos_disponibles' => $campos_disponibles,
            'payments' => $payments,
            'carriers' => $carriers,
            'order_states' => $order_states,
            'importe_347' => Configuration::get('INFORMES_CONT_IMPORTE_347', '3005.06'),
            'ajax_url' => $this->context->link->getAdminLink('AdminInformesContables'),
            'module_dir' => $this->module->getPathUri()
        ));
        
        // CAMBIO AQUÍ: Ruta correcta del template
        $this->setTemplate('informes.tpl');
    }

    public function ajaxProcessGenerarListado()
    {
        $fecha_inicio = Tools::getValue('fecha_inicio');
        $fecha_fin = Tools::getValue('fecha_fin');
        $campos = Tools::getValue('campos');
        $filtros = Tools::getValue('filtros');
        $formato = Tools::getValue('formato'); // excel, pdf, pantalla
        
        // Validar fechas
        if (!Validate::isDate($fecha_inicio) || !Validate::isDate($fecha_fin)) {
            die(json_encode(array('error' => $this->l('Fechas inválidas'))));
        }
        
        // Construir consulta
        $sql = $this->buildListadoQuery($fecha_inicio, $fecha_fin, $campos, $filtros);
        $results = Db::getInstance()->executeS($sql);
        
        // Registrar en log
        $this->logInforme('listado_personalizado', array(
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'campos' => $campos,
            'filtros' => $filtros
        ));
        
        // NUEVO: Si es formato pantalla, devolver solo los datos
        if ($formato == 'pantalla') {
            die(json_encode(array(
                'success' => true,
                'data' => $results,
                'count' => count($results),
                'campos' => $campos,
                'campos_disponibles' => $this->getCamposDisponibles()
            )));
        }
        
        // Generar archivo según formato (mantener funcionalidad original)
        if ($formato == 'excel') {
            $file = $this->generateExcel($results, $campos);
        } else {
            $file = $this->generatePDF($results, $campos);
        }
        
        die(json_encode(array(
            'success' => true,
            'file' => $file,
            'count' => count($results)
        )));
    }

    public function ajaxProcessGenerar347()
    {
        $year = Tools::getValue('year');
        $importe_minimo = (float)Tools::getValue('importe_minimo');
        
        if (!$year || $year < 2000 || $year > date('Y')) {
            die(json_encode(array('error' => $this->l('Año inválido'))));
        }
        
        // Obtener datos del 347
        $sql = "SELECT 
                c.id_customer,
                c.company,
                c.firstname,
                c.lastname,
                c.email,
                a.company as address_company,
                a.vat_number,
                a.address1,
                a.address2,
                a.postcode,
                a.city,
                cl.name as country,
                SUM(o.total_paid_tax_incl) as total_anual
            FROM "._DB_PREFIX_."orders o
            INNER JOIN "._DB_PREFIX_."customer c ON o.id_customer = c.id_customer
            INNER JOIN "._DB_PREFIX_."address a ON o.id_address_invoice = a.id_address
            INNER JOIN "._DB_PREFIX_."country_lang cl ON a.id_country = cl.id_country AND cl.id_lang = ".(int)$this->context->language->id."
            WHERE YEAR(o.date_add) = ".(int)$year."
                AND o.valid = 1
            GROUP BY c.id_customer
            HAVING total_anual >= ".(float)$importe_minimo."
            ORDER BY total_anual DESC";
            
        $results = Db::getInstance()->executeS($sql);
        
        // Separar empresas de particulares
        $empresas = array();
        $particulares = array();
        
        foreach ($results as $row) {
            // Es empresa si tiene company Y vat_number
            if (!empty($row['company']) && !empty($row['address_company']) && !empty($row['vat_number'])) {
                $empresas[] = $row;
            } else {
                $particulares[] = $row;
            }
        }
        
        // Registrar en log
        $this->logInforme('modelo_347', array(
            'year' => $year,
            'importe_minimo' => $importe_minimo,
            'total_registros' => count($results)
        ));
        
        die(json_encode(array(
            'success' => true,
            'empresas' => $empresas,
            'particulares' => $particulares,
            'total' => count($results),
            'year' => $year
        )));
    }

    public function ajaxProcessExportar347()
    {
        $data = Tools::getValue('data');
        $formato = Tools::getValue('formato'); // excel, pdf
        
        $data = json_decode($data, true);
        
        if ($formato == 'excel') {
            $file = $this->generate347Excel($data);
        } else {
            $file = $this->generate347PDF($data);
        }
        
        die(json_encode(array(
            'success' => true,
            'file' => $file
        )));
    }

    public function ajaxProcessEnviarEmails347()
    {
        $clientes = json_decode(Tools::getValue('clientes'), true);
        $asunto = Tools::getValue('asunto');
        $mensaje = Tools::getValue('mensaje');
        $incluir_solo_cliente = Tools::getValue('incluir_solo_cliente') == 'true';
        
        $enviados = 0;
        $errores = 0;
        
        foreach ($clientes as $cliente) {
            // Obtener detalle de facturas del cliente
            $facturas = $this->getFacturasCliente($cliente['id_customer'], date('Y'));
            
            // Preparar datos para el email
            $template_vars = array(
                '{nombre}' => $cliente['firstname'].' '.$cliente['lastname'],
                '{empresa}' => $cliente['company'] ?: '',
                '{total_anual}' => Tools::displayPrice($cliente['total_anual']),
                '{year}' => date('Y'),
                '{facturas}' => $this->formatFacturasEmail($facturas)
            );
            
            // Reemplazar variables en mensaje
            $mensaje_final = strtr($mensaje, $template_vars);
            
            // Enviar email
            $result = Mail::Send(
                $this->context->language->id,
                'contact',
                $asunto,
                array(
                    '{message}' => nl2br($mensaje_final)
                ),
                $cliente['email'],
                $cliente['firstname'].' '.$cliente['lastname'],
                null,
                null,
                null,
                null,
                _PS_MAIL_DIR_,
                false,
                $this->context->shop->id
            );
            
            if ($result) {
                $enviados++;
                $this->logEmail($cliente['id_customer'], 'modelo_347', $cliente['email'], 'enviado');
            } else {
                $errores++;
                $this->logEmail($cliente['id_customer'], 'modelo_347', $cliente['email'], 'error');
            }
        }
        
        die(json_encode(array(
            'success' => true,
            'enviados' => $enviados,
            'errores' => $errores
        )));
    }

    private function getCamposDisponibles()
    {
        return array(
            'order' => array(
                'id_order' => $this->l('Nº Pedido'),
                'reference' => $this->l('Referencia'),
                'date_add' => $this->l('Fecha'),
                'invoice_number' => $this->l('Nº Factura'),
                'invoice_date' => $this->l('Fecha Factura'),
                'payment' => $this->l('Forma de Pago'),
                'total_paid_tax_excl' => $this->l('Base Imponible'),
                'total_paid_tax_incl' => $this->l('Total con IVA'),
                'total_paid' => $this->l('Total Pagado'),
                'total_shipping_tax_excl' => $this->l('Envío sin IVA'),
                'total_shipping_tax_incl' => $this->l('Envío con IVA'),
                'carrier_name' => $this->l('Transportista'),
                'current_state' => $this->l('Estado')
            ),
            'customer' => array(
                'id_customer' => $this->l('ID Cliente'),
                'firstname' => $this->l('Nombre'),
                'lastname' => $this->l('Apellidos'),
                'email' => $this->l('Email'),
                'company' => $this->l('Empresa')
            ),
            'address' => array(
                'company' => $this->l('Empresa (Dirección)'),
                'vat_number' => $this->l('CIF/NIF'),
                'address1' => $this->l('Dirección 1'),
                'address2' => $this->l('Dirección 2'),
                'postcode' => $this->l('Código Postal'),
                'city' => $this->l('Ciudad'),
                'country' => $this->l('País'),
                'phone' => $this->l('Teléfono'),
                'phone_mobile' => $this->l('Móvil')
            ),
            'tax' => array(
                'tax_4' => $this->l('IVA 4%'),
                'tax_10' => $this->l('IVA 10%'),
                'tax_21' => $this->l('IVA 21%')
            )
        );
    }

    private function buildListadoQuery($fecha_inicio, $fecha_fin, $campos, $filtros)
    {
        $select = array();
        $joins = array();
        $where = array();
        
        // Siempre incluir dirección de facturación
        $joins[] = "INNER JOIN "._DB_PREFIX_."address a ON o.id_address_invoice = a.id_address";
        $joins[] = "INNER JOIN "._DB_PREFIX_."country_lang cl ON a.id_country = cl.id_country AND cl.id_lang = ".(int)$this->context->language->id;
        
        // Construir SELECT según campos seleccionados
        foreach ($campos as $table => $fields) {
            foreach ($fields as $field) {
                switch ($table) {
                    case 'order':
                        if ($field == 'carrier_name') {
                            $select[] = "ca.name as carrier_name";
                            $joins[] = "LEFT JOIN "._DB_PREFIX_."carrier ca ON o.id_carrier = ca.id_carrier";
                        } elseif ($field == 'current_state') {
                            $select[] = "osl.name as current_state";
                            $joins[] = "LEFT JOIN "._DB_PREFIX_."order_state_lang osl ON o.current_state = osl.id_order_state AND osl.id_lang = ".(int)$this->context->language->id;
                        } else {
                            $select[] = "o.".$field;
                        }
                        break;
                    case 'customer':
                        $select[] = "c.".$field;
                        if (!in_array("INNER JOIN "._DB_PREFIX_."customer c ON o.id_customer = c.id_customer", $joins)) {
                            $joins[] = "INNER JOIN "._DB_PREFIX_."customer c ON o.id_customer = c.id_customer";
                        }
                        break;
                    case 'address':
                        if ($field == 'country') {
                            $select[] = "cl.name as country";
                        } else {
                            $select[] = "a.".$field;
                        }
                        break;
                    case 'tax':
                        // Calcular IVA por tipos
                        if ($field == 'tax_4') {
                            $select[] = "(SELECT SUM(od.total_price_tax_incl - od.total_price_tax_excl) 
                                         FROM "._DB_PREFIX_."order_detail od 
                                         WHERE od.id_order = o.id_order AND od.tax_rate = 4) as tax_4";
                        } elseif ($field == 'tax_10') {
                            $select[] = "(SELECT SUM(od.total_price_tax_incl - od.total_price_tax_excl) 
                                         FROM "._DB_PREFIX_."order_detail od 
                                         WHERE od.id_order = o.id_order AND od.tax_rate = 10) as tax_10";
                        } elseif ($field == 'tax_21') {
                            $select[] = "(SELECT SUM(od.total_price_tax_incl - od.total_price_tax_excl) 
                                         FROM "._DB_PREFIX_."order_detail od 
                                         WHERE od.id_order = o.id_order AND od.tax_rate = 21) as tax_21";
                        }
                        break;
                }
            }
        }
        
        // Siempre incluir algunos campos básicos de dirección
        $select[] = "a.company as address_company";
        $select[] = "a.vat_number";
        
        // WHERE básico
        $where[] = "o.date_add BETWEEN '".pSQL($fecha_inicio)." 00:00:00' AND '".pSQL($fecha_fin)." 23:59:59'";
        $where[] = "o.valid = 1";
        
        // Aplicar filtros
        if (!empty($filtros['payment'])) {
            $where[] = "o.payment IN ('".implode("','", array_map('pSQL', $filtros['payment']))."')";
        }
        if (!empty($filtros['carrier'])) {
            $where[] = "o.id_carrier IN (".implode(",", array_map('intval', $filtros['carrier'])).")";
        }
        if (!empty($filtros['state'])) {
            $where[] = "o.current_state IN (".implode(",", array_map('intval', $filtros['state'])).")";
        }
        if (!empty($filtros['solo_empresas'])) {
            $where[] = "a.company != '' AND a.vat_number != ''";
        }
        
        $sql = "SELECT ".implode(", ", $select)."
                FROM "._DB_PREFIX_."orders o
                ".implode(" ", array_unique($joins))."
                WHERE ".implode(" AND ", $where)."
                ORDER BY o.date_add DESC";
                
        return $sql;
    }

    // NUEVO: Método para generar CSV simple (sin PHPExcel)
    private function generateCSV($data, $campos)
    {
        $filename = 'informe_contable_'.date('Y-m-d_His').'.csv';
        $filepath = _PS_MODULE_DIR_.'informescontables/exports/'.$filename;
        
        $file = fopen($filepath, 'w');
        
        // BOM para UTF-8
        fwrite($file, "\xEF\xBB\xBF");
        
        // Cabeceras
        $headers = array();
        $campos_disponibles = $this->getCamposDisponibles();
        foreach ($campos as $table => $fields) {
            foreach ($fields as $field) {
                $headers[] = $campos_disponibles[$table][$field];
            }
        }
        $headers[] = 'Es Empresa';
        fputcsv($file, $headers, ';');
        
        // Datos
        foreach ($data as $item) {
            $row = array();
            foreach ($campos as $table => $fields) {
                foreach ($fields as $field) {
                    $value = isset($item[$field]) ? $item[$field] : '';
                    // Formatear precios
                    if (strpos($field, 'total_') !== false || strpos($field, 'tax_') !== false) {
                        $value = number_format((float)$value, 2, ',', '.');
                    }
                    $row[] = $value;
                }
            }
            // Es empresa
            $es_empresa = (!empty($item['address_company']) && !empty($item['vat_number'])) ? 'Sí' : 'No';
            $row[] = $es_empresa;
            fputcsv($file, $row, ';');
        }
        
        fclose($file);
        
        return $this->module->getPathUri().'exports/'.$filename;
    }

    // Mantener el método original generateExcel para cuando tengas PHPExcel
    private function generateExcel($data, $campos)
    {
        // Si PHPExcel no está disponible, usar CSV
        if (!file_exists(_PS_MODULE_DIR_.'informescontables/libraries/PHPExcel.php')) {
            return $this->generateCSV($data, $campos);
        }
        
        require_once(_PS_MODULE_DIR_.'informescontables/libraries/PHPExcel.php');
        
        $objPHPExcel = new PHPExcel();
        $objPHPExcel->getProperties()
            ->setCreator("PrestaShop")
            ->setTitle("Informe Contable")
            ->setSubject("Listado personalizado");
            
        $sheet = $objPHPExcel->getActiveSheet();
        
        // Cabeceras
        $col = 0;
        $campos_disponibles = $this->getCamposDisponibles();
        foreach ($campos as $table => $fields) {
            foreach ($fields as $field) {
                $sheet->setCellValueByColumnAndRow($col, 1, $campos_disponibles[$table][$field]);
                $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
                $col++;
            }
        }
        
        // Añadir columna "Es Empresa"
        $sheet->setCellValueByColumnAndRow($col, 1, 'Es Empresa');
        $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        
        // Datos
        $row = 2;
        foreach ($data as $item) {
            $col = 0;
            foreach ($campos as $table => $fields) {
                foreach ($fields as $field) {
                    $value = isset($item[$field]) ? $item[$field] : '';
                    // Formatear precios
                    if (strpos($field, 'total_') !== false || strpos($field, 'tax_') !== false) {
                        $value = number_format((float)$value, 2, ',', '.');
                    }
                    $sheet->setCellValueByColumnAndRow($col, $row, $value);
                    $col++;
                }
            }
            // Añadir si es empresa
            $es_empresa = (!empty($item['address_company']) && !empty($item['vat_number'])) ? 'Sí' : 'No';
            $sheet->setCellValueByColumnAndRow($col, $row, $es_empresa);
            $row++;
        }
        
        // Estilo de cabeceras
        $sheet->getStyle('A1:'.PHPExcel_Cell::stringFromColumnIndex($col).'1')
            ->applyFromArray(array(
                'font' => array('bold' => true),
                'fill' => array(
                    'type' => PHPExcel_Style_Fill::FILL_SOLID,
                    'color' => array('rgb' => 'E0E0E0')
                )
            ));
            
        // Guardar archivo
        $filename = 'informe_contable_'.date('Y-m-d_His').'.xlsx';
        $filepath = _PS_MODULE_DIR_.'informescontables/exports/'.$filename;
        
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save($filepath);
        
        return $this->module->getPathUri().'exports/'.$filename;
    }

    private function generatePDF($data, $campos)
    {
        require_once(_PS_TOOL_DIR_.'tcpdf/tcpdf.php');
        
        $pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('PrestaShop');
        $pdf->SetAuthor('Informes Contables');
        $pdf->SetTitle('Informe Contable');
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        
        // Título
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Informe Contable', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Generado el '.date('d/m/Y H:i'), 0, 1, 'C');
        $pdf->Ln(5);
        
        // Tabla
        $pdf->SetFont('helvetica', '', 8);
        
        // Calcular anchos de columna
        $campos_disponibles = $this->getCamposDisponibles();
        $num_campos = 0;
        foreach ($campos as $table => $fields) {
            $num_campos += count($fields);
        }
        $num_campos++; // Para "Es Empresa"
        $col_width = 280 / $num_campos; // Ancho página horizontal
        
        // Cabeceras
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('helvetica', 'B', 8);
        foreach ($campos as $table => $fields) {
            foreach ($fields as $field) {
                $pdf->Cell($col_width, 7, $campos_disponibles[$table][$field], 1, 0, 'C', true);
            }
        }
        $pdf->Cell($col_width, 7, 'Es Empresa', 1, 1, 'C', true);
        
        // Datos
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetFillColor(255, 255, 255);
        foreach ($data as $item) {
            foreach ($campos as $table => $fields) {
                foreach ($fields as $field) {
                    $value = isset($item[$field]) ? $item[$field] : '';
                    // Formatear precios
                    if (strpos($field, 'total_') !== false || strpos($field, 'tax_') !== false) {
                        $value = number_format((float)$value, 2, ',', '.').'€';
                    }
                    $pdf->Cell($col_width, 6, $value, 1, 0, 'L');
                }
            }
            // Es empresa
            $es_empresa = (!empty($item['address_company']) && !empty($item['vat_number'])) ? 'Sí' : 'No';
            $pdf->Cell($col_width, 6, $es_empresa, 1, 1, 'C');
        }
        
        // Guardar archivo
        $filename = 'informe_contable_'.date('Y-m-d_His').'.pdf';
        $filepath = _PS_MODULE_DIR_.'informescontables/exports/'.$filename;
        $pdf->Output($filepath, 'F');
        
        return $this->module->getPathUri().'exports/'.$filename;
    }

    private function generate347Excel($data)
    {
        // Si PHPExcel no está disponible, usar CSV
        if (!file_exists(_PS_MODULE_DIR_.'informescontables/libraries/PHPExcel.php')) {
            return $this->generate347CSV($data);
        }
        
        require_once(_PS_MODULE_DIR_.'informescontables/libraries/PHPExcel.php');
        
        $objPHPExcel = new PHPExcel();
        $objPHPExcel->getProperties()
            ->setCreator("PrestaShop")
            ->setTitle("Modelo 347")
            ->setSubject("Declaración anual de operaciones con terceros");
            
        $sheet = $objPHPExcel->getActiveSheet();
        $sheet->setTitle('Modelo 347');
        
        // Cabeceras
        $headers = array(
            'Tipo',
            'CIF/NIF',
            'Nombre/Razón Social',
            'Dirección',
            'Código Postal',
            'Ciudad',
            'País',
            'Email',
            'Total Anual',
            'Trimestre 1',
            'Trimestre 2',
            'Trimestre 3',
            'Trimestre 4'
        );
        
        $col = 0;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, 1, $header);
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            $col++;
        }
        
        // Estilo cabeceras
        $sheet->getStyle('A1:M1')->applyFromArray(array(
            'font' => array('bold' => true),
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => 'E0E0E0')
            )
        ));
        
        $row = 2;
        
        // Empresas
        if (!empty($data['empresas'])) {
            foreach ($data['empresas'] as $empresa) {
                $trimestres = $this->getTrimestralesCliente($empresa['id_customer'], $data['year']);
                
                $sheet->setCellValueByColumnAndRow(0, $row, 'EMPRESA');
                $sheet->setCellValueByColumnAndRow(1, $row, $empresa['vat_number']);
                $sheet->setCellValueByColumnAndRow(2, $row, $empresa['company'] ?: $empresa['address_company']);
                $sheet->setCellValueByColumnAndRow(3, $row, $empresa['address1'].' '.$empresa['address2']);
                $sheet->setCellValueByColumnAndRow(4, $row, $empresa['postcode']);
                $sheet->setCellValueByColumnAndRow(5, $row, $empresa['city']);
                $sheet->setCellValueByColumnAndRow(6, $row, $empresa['country']);
                $sheet->setCellValueByColumnAndRow(7, $row, $empresa['email']);
                $sheet->setCellValueByColumnAndRow(8, $row, number_format($empresa['total_anual'], 2, ',', '.'));
                $sheet->setCellValueByColumnAndRow(9, $row, number_format($trimestres['Q1'], 2, ',', '.'));
                $sheet->setCellValueByColumnAndRow(10, $row, number_format($trimestres['Q2'], 2, ',', '.'));
                $sheet->setCellValueByColumnAndRow(11, $row, number_format($trimestres['Q3'], 2, ',', '.'));
                $sheet->setCellValueByColumnAndRow(12, $row, number_format($trimestres['Q4'], 2, ',', '.'));
                $row++;
            }
        }
        
        // Particulares
        if (!empty($data['particulares'])) {
            foreach ($data['particulares'] as $particular) {
                $trimestres = $this->getTrimestralesCliente($particular['id_customer'], $data['year']);
                
                $sheet->setCellValueByColumnAndRow(0, $row, 'PARTICULAR');
                $sheet->setCellValueByColumnAndRow(1, $row, $particular['vat_number'] ?: 'N/D');
                $sheet->setCellValueByColumnAndRow(2, $row, $particular['firstname'].' '.$particular['lastname']);
                $sheet->setCellValueByColumnAndRow(3, $row, $particular['address1'].' '.$particular['address2']);
                $sheet->setCellValueByColumnAndRow(4, $row, $particular['postcode']);
                $sheet->setCellValueByColumnAndRow(5, $row, $particular['city']);
                $sheet->setCellValueByColumnAndRow(6, $row, $particular['country']);
                $sheet->setCellValueByColumnAndRow(7, $row, $particular['email']);
                $sheet->setCellValueByColumnAndRow(8, $row, number_format($particular['total_anual'], 2, ',', '.'));
                $sheet->setCellValueByColumnAndRow(9, $row, number_format($trimestres['Q1'], 2, ',', '.'));
                $sheet->setCellValueByColumnAndRow(10, $row, number_format($trimestres['Q2'], 2, ',', '.'));
                $sheet->setCellValueByColumnAndRow(11, $row, number_format($trimestres['Q3'], 2, ',', '.'));
                $sheet->setCellValueByColumnAndRow(12, $row, number_format($trimestres['Q4'], 2, ',', '.'));
                $row++;
            }
        }
        
        // Totales
        $row++;
        $sheet->setCellValueByColumnAndRow(0, $row, 'TOTALES');
        $sheet->getStyle('A'.$row)->getFont()->setBold(true);
        $sheet->setCellValueByColumnAndRow(7, $row, 'Total Empresas:');
        $sheet->setCellValueByColumnAndRow(8, $row, count($data['empresas']));
        $row++;
        $sheet->setCellValueByColumnAndRow(7, $row, 'Total Particulares:');
        $sheet->setCellValueByColumnAndRow(8, $row, count($data['particulares']));
        $row++;
        $sheet->setCellValueByColumnAndRow(7, $row, 'TOTAL GENERAL:');
        $sheet->getStyle('H'.$row.':I'.$row)->getFont()->setBold(true);
        $sheet->setCellValueByColumnAndRow(8, $row, count($data['empresas']) + count($data['particulares']));
        
        // Guardar
        $filename = 'modelo_347_'.$data['year'].'_'.date('Y-m-d_His').'.xlsx';
        $filepath = _PS_MODULE_DIR_.'informescontables/exports/'.$filename;
        
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save($filepath);
        
        return $this->module->getPathUri().'exports/'.$filename;
    }

    // NUEVO: Método CSV para Modelo 347 (sin PHPExcel)
    private function generate347CSV($data)
    {
        $filename = 'modelo_347_'.$data['year'].'_'.date('Y-m-d_His').'.csv';
        $filepath = _PS_MODULE_DIR_.'informescontables/exports/'.$filename;
        
        $file = fopen($filepath, 'w');
        
        // BOM para UTF-8
        fwrite($file, "\xEF\xBB\xBF");
        
        // Cabeceras
        $headers = array(
            'Tipo',
            'CIF/NIF',
            'Nombre/Razón Social',
            'Dirección',
            'Código Postal',
            'Ciudad',
            'País',
            'Email',
            'Total Anual',
            'Trimestre 1',
            'Trimestre 2',
            'Trimestre 3',
            'Trimestre 4'
        );
        fputcsv($file, $headers, ';');
        
        // Empresas
        if (!empty($data['empresas'])) {
            foreach ($data['empresas'] as $empresa) {
                $trimestres = $this->getTrimestralesCliente($empresa['id_customer'], $data['year']);
                
                $row = array(
                    'EMPRESA',
                    $empresa['vat_number'],
                    $empresa['company'] ?: $empresa['address_company'],
                    $empresa['address1'].' '.$empresa['address2'],
                    $empresa['postcode'],
                    $empresa['city'],
                    $empresa['country'],
                    $empresa['email'],
                    number_format($empresa['total_anual'], 2, ',', '.'),
                    number_format($trimestres['Q1'], 2, ',', '.'),
                    number_format($trimestres['Q2'], 2, ',', '.'),
                    number_format($trimestres['Q3'], 2, ',', '.'),
                    number_format($trimestres['Q4'], 2, ',', '.')
                );
                fputcsv($file, $row, ';');
            }
        }
        
        // Particulares
        if (!empty($data['particulares'])) {
            foreach ($data['particulares'] as $particular) {
                $trimestres = $this->getTrimestralesCliente($particular['id_customer'], $data['year']);
                
                $row = array(
                    'PARTICULAR',
                    $particular['vat_number'] ?: 'N/D',
                    $particular['firstname'].' '.$particular['lastname'],
                    $particular['address1'].' '.$particular['address2'],
                    $particular['postcode'],
                    $particular['city'],
                    $particular['country'],
                    $particular['email'],
                    number_format($particular['total_anual'], 2, ',', '.'),
                    number_format($trimestres['Q1'], 2, ',', '.'),
                    number_format($trimestres['Q2'], 2, ',', '.'),
                    number_format($trimestres['Q3'], 2, ',', '.'),
                    number_format($trimestres['Q4'], 2, ',', '.')
                );
                fputcsv($file, $row, ';');
            }
        }
        
        fclose($file);
        
        return $this->module->getPathUri().'exports/'.$filename;
    }

    private function generate347PDF($data)
    {
        require_once(_PS_TOOL_DIR_.'tcpdf/tcpdf.php');
        
        $pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('PrestaShop');
        $pdf->SetAuthor('Informes Contables');
        $pdf->SetTitle('Modelo 347 - Año '.$data['year']);
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        
        // Título
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 10, 'MODELO 347', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 8, 'Declaración anual de operaciones con terceros', 0, 1, 'C');
        $pdf->Cell(0, 6, 'Año: '.$data['year'], 0, 1, 'C');
        $pdf->Ln(5);
        
        // Empresas
        if (!empty($data['empresas'])) {
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 8, 'EMPRESAS', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 9);
            
            // Cabeceras tabla
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(25, 7, 'CIF', 1, 0, 'C', true);
            $pdf->Cell(60, 7, 'Razón Social', 1, 0, 'C', true);
            $pdf->Cell(50, 7, 'Dirección', 1, 0, 'C', true);
            $pdf->Cell(15, 7, 'C.P.', 1, 0, 'C', true);
            $pdf->Cell(30, 7, 'Ciudad', 1, 0, 'C', true);
            $pdf->Cell(50, 7, 'Email', 1, 0, 'C', true);
            $pdf->Cell(30, 7, 'Total Anual', 1, 1, 'C', true);
            
            $pdf->SetFont('helvetica', '', 8);
            foreach ($data['empresas'] as $empresa) {
                $pdf->Cell(25, 6, $empresa['vat_number'], 1, 0, 'L');
                $pdf->Cell(60, 6, $empresa['company'] ?: $empresa['address_company'], 1, 0, 'L');
                $pdf->Cell(50, 6, substr($empresa['address1'], 0, 30), 1, 0, 'L');
                $pdf->Cell(15, 6, $empresa['postcode'], 1, 0, 'C');
                $pdf->Cell(30, 6, $empresa['city'], 1, 0, 'L');
                $pdf->Cell(50, 6, $empresa['email'], 1, 0, 'L');
                $pdf->Cell(30, 6, number_format($empresa['total_anual'], 2, ',', '.').'€', 1, 1, 'R');
            }
            $pdf->Ln(5);
        }
        
        // Particulares
        if (!empty($data['particulares'])) {
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 8, 'PARTICULARES', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 9);
            
            // Cabeceras tabla
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(25, 7, 'NIF', 1, 0, 'C', true);
            $pdf->Cell(60, 7, 'Nombre', 1, 0, 'C', true);
            $pdf->Cell(50, 7, 'Dirección', 1, 0, 'C', true);
            $pdf->Cell(15, 7, 'C.P.', 1, 0, 'C', true);
            $pdf->Cell(30, 7, 'Ciudad', 1, 0, 'C', true);
            $pdf->Cell(50, 7, 'Email', 1, 0, 'C', true);
            $pdf->Cell(30, 7, 'Total Anual', 1, 1, 'C', true);
            
            $pdf->SetFont('helvetica', '', 8);
            foreach ($data['particulares'] as $particular) {
                $pdf->Cell(25, 6, $particular['vat_number'] ?: 'N/D', 1, 0, 'L');
                $pdf->Cell(60, 6, $particular['firstname'].' '.$particular['lastname'], 1, 0, 'L');
                $pdf->Cell(50, 6, substr($particular['address1'], 0, 30), 1, 0, 'L');
                $pdf->Cell(15, 6, $particular['postcode'], 1, 0, 'C');
                $pdf->Cell(30, 6, $particular['city'], 1, 0, 'L');
                $pdf->Cell(50, 6, $particular['email'], 1, 0, 'L');
                $pdf->Cell(30, 6, number_format($particular['total_anual'], 2, ',', '.').'€', 1, 1, 'R');
            }
        }
        
        // Resumen
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'RESUMEN', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(50, 6, 'Total Empresas:', 0, 0, 'L');
        $pdf->Cell(30, 6, count($data['empresas']), 0, 1, 'L');
        $pdf->Cell(50, 6, 'Total Particulares:', 0, 0, 'L');
        $pdf->Cell(30, 6, count($data['particulares']), 0, 1, 'L');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(50, 6, 'TOTAL DECLARANTES:', 0, 0, 'L');
        $pdf->Cell(30, 6, count($data['empresas']) + count($data['particulares']), 0, 1, 'L');
        
        // Guardar
        $filename = 'modelo_347_'.$data['year'].'_'.date('Y-m-d_His').'.pdf';
        $filepath = _PS_MODULE_DIR_.'informescontables/exports/'.$filename;
        $pdf->Output($filepath, 'F');
        
        return $this->module->getPathUri().'exports/'.$filename;
    }

    private function getTrimestralesCliente($id_customer, $year)
    {
        $trimestres = array('Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0);
        
        $sql = "SELECT 
                QUARTER(o.date_add) as trimestre,
                SUM(o.total_paid_tax_incl) as total
            FROM "._DB_PREFIX_."orders o
            WHERE o.id_customer = ".(int)$id_customer."
                AND YEAR(o.date_add) = ".(int)$year."
                AND o.valid = 1
            GROUP BY QUARTER(o.date_add)";
            
        $results = Db::getInstance()->executeS($sql);
        
        foreach ($results as $row) {
            $trimestres['Q'.$row['trimestre']] = $row['total'];
        }
        
        return $trimestres;
    }

    private function getFacturasCliente($id_customer, $year)
    {
        $sql = "SELECT 
                o.id_order,
                o.reference,
                o.invoice_number,
                o.invoice_date,
                o.total_paid_tax_excl,
                o.total_paid_tax_incl,
                o.date_add
            FROM "._DB_PREFIX_."orders o
            WHERE o.id_customer = ".(int)$id_customer."
                AND YEAR(o.date_add) = ".(int)$year."
                AND o.valid = 1
                AND o.invoice_number > 0
            ORDER BY o.invoice_date ASC";
            
        return Db::getInstance()->executeS($sql);
    }

    private function formatFacturasEmail($facturas)
    {
        $html = '<table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%;">';
        $html .= '<tr style="background-color: #f0f0f0;">
                    <th>Nº Factura</th>
                    <th>Fecha</th>
                    <th>Base Imponible</th>
                    <th>Total con IVA</th>
                  </tr>';
                  
        foreach ($facturas as $factura) {
            $html .= '<tr>
                        <td>'.$factura['invoice_number'].'</td>
                        <td>'.date('d/m/Y', strtotime($factura['invoice_date'])).'</td>
                        <td>'.Tools::displayPrice($factura['total_paid_tax_excl']).'</td>
                        <td>'.Tools::displayPrice($factura['total_paid_tax_incl']).'</td>
                      </tr>';
        }
        
        $html .= '</table>';
        
        return $html;
    }

    private function logInforme($tipo, $parametros)
    {
        Db::getInstance()->insert('informes_contables_log', array(
            'tipo_informe' => pSQL($tipo),
            'fecha_generacion' => date('Y-m-d H:i:s'),
            'id_employee' => (int)$this->context->employee->id,
            'parametros' => pSQL(json_encode($parametros))
        ));
    }

    private function logEmail($id_customer, $tipo, $email, $estado)
    {
        Db::getInstance()->insert('informes_contables_emails', array(
            'id_customer' => (int)$id_customer,
            'tipo_informe' => pSQL($tipo),
            'fecha_envio' => date('Y-m-d H:i:s'),
            'email' => pSQL($email),
            'estado' => pSQL($estado)
        ));
    }
}