<?php
class Numerador extends Model
{
    public function siguiente(string $tipo): int
    {
        $stmt = $this->db->prepare("
            SELECT ultimo_numero FROM numeradores WHERE tipo = ? FOR UPDATE
        ");
        $stmt->execute([$tipo]);
        $num = (int)$stmt->fetchColumn() + 1;

        $this->db->prepare("
            UPDATE numeradores SET ultimo_numero = ? WHERE tipo = ?
        ")->execute([$num, $tipo]);

        return $num;
    }
}
