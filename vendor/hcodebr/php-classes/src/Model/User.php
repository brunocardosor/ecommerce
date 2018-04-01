<?php 

namespace Hcode\Model;

use Exception;
use \Hcode\Model;
use Hcode\Mailer;
use \Hcode\DB\Sql;

class User extends Model
{

	const SESSION = "User";
	const KEYGEN = "SOBREOSABIASABIA";

	public static function login($login, $password)
	{

		$sql = new Sql();

		$results = $sql->select("SELECT * FROM tb_users WHERE deslogin = :LOGIN", array(
			":LOGIN" => $login
		));

		if (count($results) === 0) {
			throw new \Exception("Usuário inexistente ou senha inválida.");
		}

		$data = $results[0];

		if (password_verify($password, $data["despassword"]) === true) {

			$user = new User();

			$user->setData($data);

			$_SESSION[User::SESSION] = $user->getValues();

			return $user;

		} else {
			throw new \Exception("Usuário inexistente ou senha inválida.");
		}

	}

	public static function verifyLogin($inadmin = true)
	{

		if (!isset($_SESSION[User::SESSION])
			||
			!$_SESSION[User::SESSION]
			||
			!(int)$_SESSION[User::SESSION]["iduser"] > 0
			||
			(bool)$_SESSION[User::SESSION]["inadmin"] !== $inadmin) {

			header("Location: /admin/login");
			exit;

		}

	}

	public static function logout()
	{

		$_SESSION[User::SESSION] = null;

	}

	public static function listAll()
	{
		$sql = new Sql();
		return $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) ORDER BY desperson");
	}

	public function get($iduser)
	{
		$sql = new Sql();
		$result = $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) WHERE a.iduser=:iduser", array(
			':iduser' => $iduser
		));

		$this->setData($result[0]);
	}

	public function save()
	{
		$sql = new Sql();
		$results = $sql->select('CALL sp_users_save(:desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)', array(
			":desperson" => $this->getdesperson(),
			":deslogin" => $this->getdeslogin(),
			":despassword" => $this->getdespassword(),
			":desemail" => $this->getdesemail(),
			":nrphone" => $this->getnrphone(),
			":inadmin" => $this->getinadmin()
		));

		$this->setData($results[0]);
	}

	public function update()
	{

		$sql = new Sql();
		$results = $sql->select('CALL sp_usersupdate_save(
			:iduser, :desperson, :deslogin, 
			:despassword, :desemail, :nrphone, 
			:inadmin)', array(
			":iduser" => $this->getiduser(),
			":desperson" => $this->getdesperson(),
			":deslogin" => $this->getdeslogin(),
			":despassword" => $this->getdespassword(),
			":desemail" => $this->getdesemail(),
			":nrphone" => $this->getnrphone(),
			":inadmin" => $this->getinadmin()
		));

		$this->setData($results[0]);
	}

	public function delete()
	{
		$sql = new Sql();
		$results = $sql->query('CALL sp_users_delete(
			:piduser
		)', array(
			':piduser' => $this->getiduser()
		));
	}

	public static function getForgot($email, $inadmin = true)
	{
		$sql = new Sql();

		$results = $sql->select('SELECT * FROM tb_persons a INNER JOIN tb_users b USING(idperson) WHERE a.desemail = :email', array(
			":email" => $email
		));

		if (count($results) === 0) {
			throw new \Exception('Não foi possivel recuperar a senha.');
		} else {
			$dataSelect = $results[0];

			$resultQuery = $sql->select('CALL sp_userspasswordsrecoveries_create(:piduser, :pdesip)', array(
				':piduser' => $dataSelect['iduser'],
				':pdesip' => $_SERVER['REMOTE_ADDR']
			));

			if (count($resultQuery) === 0) {
				throw new \Exception("Não foi possivel recuperar as senha");
			} else {
				$dataQuery = $resultQuery[0];

				$iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
				$code = openssl_encrypt($dataQuery['idrecovery'], 'aes-256-cbc', User::KEYGEN, 0, $iv);
				$resultCode = base64_encode($iv . $code);

				if ($inadmin) {
					$link = 'http://www.loja_php7.com.br/admin/forgot/reset?code=' . $resultCode;
				} else {
					$link = 'http://www.loja_php7.com.br/forgot/reset?code=' . $resultCode;
				}
				$mailer = new Mailer($dataSelect['desemail'], $dataSelect['desperson'], 'Redefinir a sua senha da loja', 'forgot', array(
					"name" => $dataSelect['desperson'],
					"link" => $link
				));

				$mailer->send();

				return $dataSelect;
			}
		}
	}

	public static function validForgotDecrypt($code)
	{
		$codeDecripted = base64_decode($code);
		$code = mb_substr($codeDecripted, openssl_cipher_iv_length('aes-256-cbc'), null, '8bit');
		$iv = mb_substr($codeDecripted, 0, openssl_cipher_iv_length('aes-256-cbc'), '8bit');
		$idRecovery = openssl_decrypt($code, 'aes-256-cbc', User::KEYGEN, 0, $iv);

		$sql = new Sql();

		$results = $sql->select(
			'
		SELECT * 
		FROM tb_userspasswordsrecoveries a 
		INNER JOIN tb_users b USING(iduser) 
		INNER JOIN tb_persons c USING(idperson) 
		WHERE a.idrecovery = :idrecovery
		AND a.dtrecovery IS NULL 
		AND DATE_ADD(a.dtregister, INTERVAL 1 HOUR) >= NOW();',
			array(
				':idrecovery' => $idRecovery,
			)
		);

		if (count($results) === 0) {
			throw new Exception("Não foi possível acessar o link");
		} else {
			return $results[0];
		}
	}

	public static function setForgotUsed($idRecovery)
	{
		$sql = new Sql();

		$sql->query("UPDATE tb_userspasswordsrecoveries SET dtrecovery = NOW() WHERE idrecovery = :idrecovery", array(
			':idrecovery' => $idRecovery
		));
	}

	public function setPassword($password)
	{
		$sql = new Sql();

		$sql->query("UPDATE tb_users SET despassword = :password WHERE iduser = :iduser", array(
			':password' => $password,
			":iduser" => $this->getiduser()
		));
	}
}

?>