<?php
class Proveedor extends Model{
    public function all(){
        return $this->db->query("SELECT * FROM proveedores WHERE activo=1 ORDER BY razon_social ASC")->fetchAll();
    }

    public function find(int $id){
        $stmt = $this->db->prepare("SELECT * FROM proveedores WHERE id= :id ");
        $stmt->execute(['id'=>$id]);
        return $stmt->fetch();
    }

    public function save(array $d): int{
        try {
            $this->db->prepare("INSERT INTO proveedores (razon_social, cuit, email, telefono,contacto,rubro,localidad,direccion) 
            VALUES (:r,:c,:e,:t,:co,:ru,:l,:d)")->execute(['r'=>$d['razon_social'],'c'=>$d['cuit'],'e'=>$d['email'],'t'=>$d['telefono'],
            'co'=>$d['contacto'],'ru'=>$d['rubro'],'l'=>$d['localidad'],'d'=>$d['direccion']]);
            $_SESSION['success'] = "Proveedor creado correctamente.";
            error_log('Proveedor creado con ID '.$this->db->lastInsertId().' y data: '.print_r($d, true).' - '.__FILE__.':'.__LINE__);
            return $this->db->lastInsertId();
        } catch (Exception $th) {
            $_SESSION['error'] = "Error al crear el proveedor. Avise al administrador. ".$th->getMessage();
            error_log('Error creating provider: ' . $th->getMessage().' - '.__FILE__.':'.__LINE__);
            return 0;
        }
    }

    public function update(int $id, array $d): bool{
        try {
            $this->db->prepare("UPDATE proveedores SET razon_social = :r,cuit = :c,email = :e,telefono = :t,contacto = :co,rubro = :ru,
            localidad = :l,direccion = :d WHERE id = :id")->execute(['r'=>$d['razon_social'],'c'=>$d['cuit'],'e'=>$d['email'],'t'=>$d['telefono'],'co'=>$d['contacto'],'ru'=>$d['rubro'],
            'l'=>$d['localidad'],'d'=>$d['direccion'],'id'=>$id]);
            $_SESSION['success'] = "Proveedor actualizado correctamente.";
            error_log('Proveedor actualizado con ID '.$id.' y data: '.print_r($d, true).' - '.__FILE__.':'.__LINE__);
            return true;
        } catch (Exception $th) {
            $_SESSION['error'] = "Error al actualizar el proveedor. Avise al administrador. ".$th->getMessage();
            error_log('Error updating provider: ' . $th->getMessage().' - '.__FILE__.':'.__LINE__);
            return false;
        }
    }
}