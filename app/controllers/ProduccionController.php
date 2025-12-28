<?php
require_once BASE_PATH . '/app/core/Controller.php';
class ProduccionController extends Controller
{
    public function producir()
    {
        if ($_POST) {
            $db = Database::getInstance();
            $db->beginTransaction();

            try {
                $productoId = $_POST['producto_id'];
                $cantidad = (float)$_POST['cantidad'];

                // receta
                $receta = new Receta();
                $recetaId = $receta->getOrCreate($productoId);
                $detalle = $receta->detalle($recetaId);

                foreach ($detalle as $d) {
                    $consumo = $d['cantidad'] * $cantidad;

                    if ($d['stock_actual'] < $consumo) {
                        throw new Exception("Stock insuficiente");
                    }

                    // descontar MP
                    $db->prepare(
                        "UPDATE materias_primas 
                         SET stock_actual = stock_actual - :c
                         WHERE id = :id"
                    )->execute([
                        'c'=>$consumo,
                        'id'=>$d['materia_prima_id']
                    ]);
                }

                // sumar producto terminado (luego mejoramos)
                $db->prepare(
                    "INSERT INTO producciones 
                    (producto_id, cantidad, usuario_id)
                    VALUES (:p,:c,:u)"
                )->execute([
                    'p'=>$productoId,
                    'c'=>$cantidad,
                    'u'=>$_SESSION['user_id']
                ]);

                $db->commit();
                header('Location: '.BASE_URL.'/produccion');
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }

        $this->view('produccion/form', [
            'title'=>'Producción'
        ]);
    }
}