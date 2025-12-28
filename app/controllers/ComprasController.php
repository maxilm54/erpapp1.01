<?php
require_once BASE_PATH.'/app/core/Controller.php';

class ComprasController extends Controller{
    public function create(){
        if($_POST){
            $db = Database::getInstance();
            $db->beginTransaction();
            try{
                //insertamos cabecera de compra
                $stmt=$db->prepare("INSERT INTO compras (proveedor_id,usuario_id) VALUES (:p,:u)");
                $stmt->execute([
                    'p'=>$_POST['proveedor_id'],
                    'u'=>$_SESSION['user_id']
                ]);
                $compraId=$db->lastInsertId();
                //insertamos detalle de compra
                foreach($_POST['items'] as $item){
                    $db->prepare("INSERT INTO compra_detalle (compra_id,materia_prima_id,cantidad) VALUES (:c,:m,:q)")->execute([
                        'c'=>$compraId,
                        'm'=>$item['materia_prima_id'],
                        'q'=>$item['cantidad']
                    ]);
                    //por cada articulo genero cambio de stock
                    $db->prepare("UPDATE materias_primas SET stock_actual=stock_actual+:q WHERE id=:id")->execute([
                        'q'=>$item['cantidad'],
                        'id'=>$item['materia_prima_id']
                    ]);
                }
                $db->commit();
                header('Location: '.BASE_PATH.'/compras');
                exit();
            }catch(Exception $e){
                $db->rollBack();
                error_log($e->getMessage());
                die($e->getMessage());
            }
        }
        $this->view('compras/form',['title'=>'Nueva Compra']);
    }
}