<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/core/Model.php';
require_once BASE_PATH . '/app/helpers/StockHelper.php';
require_once BASE_PATH . '/app/models/Producto.php';
require_once BASE_PATH . '/app/models/Materiaprima.php';

class HomeController extends Controller
{
    public function index(): void
    {
        $db = Database::getInstance();
        //========
        //ALERTAS DE STOCK
        //EL STOCK DEBO TRAERLO DE STOCK Y NO DE LA TABLA materiaprima! ERROR!
        //========
        $alertasStock = $db->query("
            SELECT nombre, stock_actual, stock_minimo, stock_critico
            FROM materias_primas
            WHERE stock_actual <= stock_minimo
            ORDER BY stock_actual ASC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        // =========================
        // FINANZAS
        // =========================

        $ingresos = $db->query("
            SELECT IFNULL(SUM(monto),0)
            FROM pagos
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
        ")->fetchColumn();

        $egresos = $db->query("
            SELECT IFNULL(SUM(ocdet.precio_unitario*ocdet.cantidad),0)
            FROM ordenes_compra_detalle ocdet
            LEFT JOIN ordenes_compra occab ON occab.id = ocdet.orden_compra_id
            WHERE occab.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
        ")->fetchColumn();

        $ganancia = $ingresos - $egresos;

        // =========================
        // PRODUCCIÓN
        // =========================

        $ordenesAbiertas = $db->query("
            SELECT COUNT(*)
            FROM ordenes_produccion
            WHERE estado = 'ABIERTA'
        ")->fetchColumn();

        $ordenesVencidas = $db->query("
            SELECT COUNT(*)
            FROM ordenes_produccion
            WHERE estado = 'ABIERTA'
              AND fecha_entrega < CURDATE()
        ")->fetchColumn();

        // =========================
        // STOCK PRODUCTOS TERMINADOS
        // EL STOCK DEBO TRAERLO DE STOCK Y NO DE LA TABLA PRODUCTO! ERROR!
        // =========================

        $stockCriticoProductos = $db->query("
            SELECT COUNT(*)
            FROM productos
            WHERE stock <= stock_critico
        ")->fetchColumn();

        // =========================
        // STOCK MATERIAS PRIMAS
        //EL STOCK DEBO TRAERLO DE STOCK Y NO DE LA TABLA MATERIAPRIMA! ERROR!
        // =========================

        $stockCriticoMP = $db->query("
            SELECT COUNT(*)
            FROM materias_primas
            WHERE stock_actual <= stock_critico
        ")->fetchColumn();

        // =========================
        // VENTAS
        // =========================

        $totalVendido = $db->query("
            SELECT IFNULL(SUM(cantidad),0)
            FROM remitos_salida_detalle
        ")->fetchColumn();

        $topVendidos = $db->query("
            SELECT p.nombre, SUM(rsd.cantidad) as total
            FROM remitos_salida_detalle rsd
            JOIN productos p ON p.id = rsd.producto_id
            GROUP BY p.id
            ORDER BY total DESC
            LIMIT 3
        ")->fetchAll(PDO::FETCH_ASSOC);

        // =========================
        // PRODUCCIÓN FINALIZADA
        // =========================

        $totalProducido = $db->query("
            SELECT IFNULL(SUM(cantidad),0)
            FROM ordenes_produccion
            WHERE estado = 'FINALIZADA'
        ")->fetchColumn();

        $topProducidos = $db->query("
            SELECT p.nombre, SUM(op.cantidad) as total
            FROM ordenes_produccion op
            JOIN productos p ON p.id = op.producto_id
            WHERE op.estado = 'FINALIZADA'
            GROUP BY p.id
            ORDER BY total DESC
            LIMIT 3
        ")->fetchAll(PDO::FETCH_ASSOC);

        // =========================
        // STOCK PRODUCTOS Y MP
        // =========================
        $producto = new Producto();
        $materiaPrima = new MateriaPrima();
        
        $productosStock = $producto->getStockStatus();
        $materiasPrimasStock = $materiaPrima->getStockStatus();

        $this->view('home/index', compact(
            'ingresos',
            'egresos',
            'ganancia',
            'ordenesAbiertas',
            'ordenesVencidas',
            'stockCriticoProductos',
            'stockCriticoMP',
            'topVendidos',
            'totalVendido',
            'topProducidos',
            'totalProducido',
            'alertasStock',
            'productosStock',
            'materiasPrimasStock'
        ));
    }
}
