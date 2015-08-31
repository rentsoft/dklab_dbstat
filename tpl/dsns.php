<form action="dsns.php" method="post">
    <table width="100%">
        <thead class="form_table_head">
            <tr>
                <td width="1">ID</td>
                <td width="1">Name</td>
                <td width="1">DSN to connect <sup><a href="http://ru.php.net/manual/en/pdo.drivers.php" target="_top">?</a></sup></td>
                <td width="1">Delete?</td>
            </tr>
        </thead>
        <tbody class="form_table_body">
            <?php foreach ($_POST['dsns'] as $id => $dsn) if ($id) {?>
                <input type="hidden" name="dsns[<?=$dsn['id']?>][id]" value="<?=$dsn['id']?>" />
                <tr>
                    <td width="1%"><?=$dsn['id']?></td>
                    <td width="1%"><input type="text" name="dsns[<?=$dsn['id']?>][name]" size="20" /></td>
                    <td><input type="text" name="dsns[<?=$dsn['id']?>][value]" style="width:100%"/></td>
                    <td width="1%"><input type="checkbox" name="dsns[<?=$dsn['id']?>][delete]" value="1"/></td>
                </tr>
            <?php }?>
            <tr>
                <td>NEW</td>
                <td><input type="text" name="dsns[0][name]" size="20"/></td>
                <td><input type="text" name="dsns[0][value]" style="width:100%"/></td>
                <td><br/></td>
            </tr>
            <tr>
                <td></td>
                <td colspan="3">
                    <input type="submit" name="doSave" value="Save" />
                </td>
            </tr>
        </tbody>
    </table>
</form>
