<?php

use function Safe\error_log;

require_once BASE_PATH . '/app/core/Model.php';
class Cliente extends Model
{
    public function all(): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM clientes ORDER BY razon_social"
        );
        return $stmt->fetchAll();
    }

    public function allactive(): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM clientes WHERE activo=1 ORDER BY razon_social"
        );
        return $stmt->fetchAll();
    }
    public function cliactive($id)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM clientes WHERE id=:id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $datocli=$stmt->fetch();
        return $datocli;
    }

    public function find(int $id)
    {
        try{
            $stmt = $this->db->prepare(
            "SELECT * FROM clientes WHERE id = :id AND activo = 1"
            );
            $stmt->execute(['id' => $id]);
            $cliente=$stmt->fetch();
            if (!$cliente) {
                throw new Exception("Cliente no encontrado, o inactivo con ID: " . $id);
            }
            return $cliente;
        }catch(Exception $e){
            error_log("Error fetching client: " . $e->getMessage());
            $_SESSION['error'] = "No se pudo encontrar el cliente.".$e->getMessage();
            header('Location: ' . BASE_URL . '/clientes');
            exit;
        }

    }

    public function create(array $data): int
    {
        try {
            $sql = "INSERT INTO clientes 
            (razon_social, cuit, email, telefono, localidad, direccion, contacto, es_distribuidor,observaciones_gral,obs_financieras) 
            VALUES (:razon, :cuit, :email, :telefono, :localidad, :direccion, :contacto, :es_distribuidor,:observaciones_gral,:obs_financieras)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'razon' => $data['razon_social'],
                'cuit' => $data['cuit'],
                'email' => $data['email'],
                'telefono' => $data['telefono'],
                'localidad' => $data['localidad'],
                'direccion' => $data['direccion'],
                'contacto' => $data['contacto'],
                'es_distribuidor' => $data['es_distribuidor'],
                'observaciones_gral' => $data['observaciones_gral'] ?? null,
                'obs_financieras' => $data['obs_financieras'] ?? null,
            ]);
            $_SESSION['success'] = "Cliente creado correctamente.";
            error_log('Cliente creado con ID '.$this->db->lastInsertId().' y data: '.print_r($data, true).' - '.__FILE__.':'.__LINE__);
            return (int)$this->db->lastInsertId();
        } catch (Exception $th) {
            $_SESSION['error'] = "Error al crear el cliente. Avise al administrador. ".$th->getMessage();
            error_log('Error creating client: ' . $th->getMessage().' - '.__FILE__.':'.__LINE__);
            return 0;
        }

    }

    public function update(int $id, array $data): bool
    {
        try {
            $sql = "UPDATE clientes SET razon_social = :razon, cuit = :cuit, email = :email, telefono = :telefono, direccion = :direccion,
            localidad = :localidad, contacto = :contacto, es_distribuidor = :es_distribuidor, observaciones_gral = :observaciones_gral,
            obs_financieras = :obs_financieras
            WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'razon' => $data['razon_social'],
                'cuit' => $data['cuit'],
                'email' => $data['email'],
                'telefono' => $data['telefono'],
                'direccion' => $data['direccion'],
                'localidad' => $data['localidad'],
                'contacto' => $data['contacto'],
                'es_distribuidor' => $data['es_distribuidor'],
                'observaciones_gral' => $data['observaciones_gral'] ?? null,
                'obs_financieras' => $data['obs_financieras'] ?? null,
            ]);
            $_SESSION['success'] = "Cliente actualizado correctamente.";
            error_log('Cliente actualizado con ID '.$id.' y data: '.print_r($data, true).' - '.__FILE__.':'.__LINE__);
            return true;
        } catch (Exception $th) {
            $_SESSION['error'] = "Error al actualizar el cliente. Avise al administrador. ".$th->getMessage();
            error_log('Error updating client: ' . $th->getMessage().' - '.__FILE__.':'.__LINE__);
            return false;
        }
    }

    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE clientes SET activo = 0 WHERE id = :id"
            );
            $stmt->execute(['id' => $id]);
            $_SESSION['success'] = "Cliente inactivado correctamente.";
            error_log("Cliente inactivado con ID: $id" . __FILE__ . ':' . __LINE__);
            return true;
        } catch (Exception $e) {
            error_log("Error inactivando cliente: " . $e->getMessage(). __FILE__ . ':' . __LINE__);
            $_SESSION['error'] = "No se pudo inactivar el cliente.".$e->getMessage();
            return false;
        }
    }
    public function activar(int $id): bool
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE clientes SET activo = 1 WHERE id = :id"
            );
            $stmt->execute(['id' => $id]);
            $_SESSION['success'] = "Cliente activado correctamente.";
            error_log("Cliente activado con ID: $id" . __FILE__ . ':' . __LINE__);

            return true;
        } catch (Exception $e) {
            error_log("Error activating client: " . $e->getMessage(). __FILE__ . ':' . __LINE__);
            $_SESSION['error'] = "No se pudo activar el cliente.".$e->getMessage();
            return false;
        }
    }
}