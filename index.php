<?php
/*
* A lightweight script for work with the Slack API.
*/
ini_set('display_errors', 0);
# Include our Slack interface classes
require_once 'slack-interface/class-slack.php';
require_once 'slack-interface/class-slack-access.php';
require_once 'slack-interface/class-slack-api-exception.php';
require_once 'vendor/tokens.php';
require_once 'vendor/useriddecrypt.php';

use Slack_Interface\Slack;
use Slack_Interface\Slack_API_Exception;

function db_access()
{
  $dbhost = getenv('DB_HOST');
  $dbname = getenv('DB_NAME');
  $dbuser = getenv('DB_USER_NAME');
  $dbpass = userid__decrypt(getenv('DB_PASSWORD'), ENV_PRIVATE_KEY);

  $connection = new PDO('mysql:host='.$dbhost.';dbname='.$dbname.';charset=utf8', "$dbuser", "$dbpass");

  return $connection;

}

/*
* Initializes the Slack handler object, loading the authentication
* information from a DB. If DB is not present,
* the Slack handler is initialized in a non-authenticated state.
* return Slack The Slack interface object
*/

function initialize_slack_interface()
{
  if (isset($_POST['team_id'], $_POST['channel_id'])) {
    //$team_id = $_POST['team_id']; $channel_id = $_POST['channel_id'];
    $connection = db_access();

    $access_string = select_user_lbw($_POST['team_id'], $_POST['channel_id'], $connection);
  }

  $slack = new Slack($access_string);

  # Register slash commands
  $slack->register_slash_command('/lbw', 'slack_command_lbw');

  return $slack;
}

function select_user_lbw($team_id, $channel_id, $connection)
{
  $userarr = array('userid' => null, 'username' => 'R2-D2', 'namedb' => null, 'timezone' => null);

  # check team_id
  $idtm = check_slack_team($team_id, $connection);

  if(!($idtm)) {
    # if no team return access_string with user R2-D2
    $access_string = array('team_id' => $team_id,'channel_id' => $channel_id,'user_lbw' => $userarr);

  } else {
    # check channel_id and user, if no channel or user then $this->access->get_channelid() is empty,
    $userarr = check_slack_channel($idtm, $channel_id, $connection);

    if ($userarr['userid'] == null) {
      # it will return to slack - Got it.
      $access_string = array('team_id' => null, 'channel_id' => null, 'user_lbw' => $userarr );
    } else {
      $access_string = array('team_id' => $team_id, 'channel_id' => $channel_id, 'user_lbw' => $userarr);
    }
  }

  return $access_string;
}

function check_slack_team($team_id, $connection)
{
  $statement = $connection->prepare('SELECT idtm, team_id FROM slackteams WHERE team_id = :tmid');
  $statement->execute([':tmid'=>$team_id]);

  if ($rs = $statement->fetch(PDO::FETCH_ASSOC)) {
    $_idtm = $rs['idtm'];
  } else {
    $_idtm = null;
  }

  return $_idtm;
}

function check_slack_channel($idtm, $channel_id, $connection)
{
  $userarr = array('userid' => null);

  $statement = $connection->prepare('SELECT slackchannels.idchn, slackchannels.channel_id, slackchannels.id_tm, slackusers.u_id, slackusers.id_chn FROM slackchannels, slackusers WHERE
    slackchannels.channel_id = :idchn AND slackchannels.id_tm = :idtm AND slackusers.id_chn = slackchannels.idchn AND slackusers.active = 1');
  $statement->execute(array(':idtm'=>$idtm, ':idchn'=>$channel_id));

  if ($rs = $statement->fetch(PDO::FETCH_ASSOC)) {
    $uid_botuser = $rs['u_id'];
    $id_chn = $rs['id_chn'];

    $stmt = $connection->prepare('SELECT users.username, users.namedb, userprofile.timezone FROM users, userprofile WHERE users.uid = :uid AND userprofile.u_id = :uid');
    $stmt->execute([':uid'=>$uid_botuser]);

    if ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $userarr = array('userid' => $uid_botuser, 'username' => $result['username'], 'namedb' => $result['namedb'], 'timezone' => $result['timezone'], 'idchn' => $id_chn);
    }
  }

  return $userarr;
}

/*
* @param Slack  $slack     The Slack interface object
* @param string $action    The id of the action to execute
*
* @return string   A result message to show to the user
*/
function do_action( $slack, $action ) {
  $result_message = '';

  switch ( $action ) {
        # Responds to a Slack slash command. Notice that commands are
        # registered at Slack initialization.
        case 'command':
          $slack->do_slash_command();
        break;

        default:
        break;
    }

  return $result_message;
}

/*
* A Slash commands that returns a message to the Slack channel.
*
* @return array
*/
function slack_command_lbw($text = null, $user)
{
  $textmessage = test_input($text);
  $command = array(
    "account", "balance", "in", "out", "list", "add", "tr", "user", "about", "help"
  );
  # how many command
  $command_number = count( $command );

  for ($i=0; $i < $command_number; $i++) {
    # each command has lenth
    $command_len = iconv_strlen($command[$i]);
    # compare the first N letters from text message and the name of command
    if (substr($textmessage, 0, $command_len) === $command[$i]) {
      # call if function exist
      $functionname = 'fnctn_'.$command[$i];
      if (function_exists($functionname)) {
          $sendmsg = $functionname($textmessage, $user);
          break;
      }
    } else {
     $sendmsg = fnctn_help($textmessage, $user);
    }
  }

  return array(
    'response_type' => 'ephemeral',
    'text' => $sendmsg,
  );
}

# test input
function test_input($data)
{
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  $data = strtolower($data);
  return $data;
}

function fnctn_account($textstr, $user)
{
  if ($textstr === 'account') {
    require_once 'etc/botcommand/get_account.php';

    if (function_exists('get_accountdata')) {
      $connection = db_access();
      $sendmsg = get_accountdata($user, $connection);
    } else {
      $sendmsg = 'Oops... Something went wrong.';
    }
  } else {
    $sendmsg = fnctn_help($textstr, $user);
  }

  return $sendmsg;
}

function fnctn_balance($textstr, $user)
{
  if ($textstr === 'balance') {
    require_once 'etc/botcommand/get_balance.php';

    if (function_exists('get_balancedata')) {
      $connection = db_access();
      $sendmsg = get_balancedata($user, $connection);
    } else {
      $sendmsg = 'Oops... Something went wrong.';
    }
  } else {
    $sendmsg = fnctn_help($textstr, $user);
  }

  return $sendmsg;
}

function fnctn_in($textstr, $user)
{
  if ($textstr === 'in') {
    require_once 'etc/botcommand/get_incoming.php';

    if (function_exists('get_incomingdata')) {
      $connection = db_access();
      $sendmsg = get_incomingdata($user, $connection);
    } else {
      $sendmsg = 'Oops... Something went wrong.';
    }
  } else {
    $sendmsg = fnctn_help($textstr, $user);
  }

  return $sendmsg;
}

function fnctn_out($textstr, $user)
{
  if ($textstr === 'out') {
    require_once 'etc/botcommand/get_outgoing.php';

    if (function_exists('get_expensedata')) {
      $connection = db_access();
      $sendmsg = get_expensedata($user, $connection);
    } else {
      $sendmsg = 'Oops... Something went wrong.';
    }
  } else {
    $sendmsg = fnctn_help($textstr, $user);
  }

  return $sendmsg;
}

function fnctn_list($textstr, $user)
{
  $textstr = test_input($textstr);
  $connection = db_access();

  if (substr($textstr, 0, 7) == 'list in') {
    $sendmsg = get_listin($user, $connection);
  } elseif (substr($textstr, 0, 8) == 'list out') {
    $sendmsg = get_listout($user, $connection);
  } else {
    $sendmsg = fnctn_help($textstr = null, $user);
  }

  return $sendmsg;
}

function get_listin($user, $connection)
{
  require_once 'etc/botcommand/get_listincoming.php';

  if (function_exists('get_listincomingdata')) {
    $sendmsg = get_listincomingdata($user, $connection);
  } else {
    $sendmsg = fnctn_help($textstr = null, $user);
  }

  return $sendmsg;
}

function get_listout($user, $connection)
{
  require_once 'etc/botcommand/get_listoutgoing.php';

  if (function_exists('get_listexpensedata')) {
    $sendmsg = get_listexpensedata($user, $connection);
  } else {
    $sendmsg = fnctn_help($textstr = null, $user);
  }

  return $sendmsg;
}

function fnctn_add($textstr, $user)
{
  $textstr = test_input($textstr);
  $connection = db_access();

  if (substr($textstr, 0, 6) == 'add in') {
    $sendmsg = getadd_in($textstr, $user, $connection);
  } elseif (substr($textstr, 0, 7) == 'add out') {
    $sendmsg = getadd_out($textstr, $user, $connection);
  } else {
    $sendmsg = fnctn_help($textstr = null, $user);
  }

  return $sendmsg;
}

function getadd_in($textstr, $user, $connection)
{
  require_once 'etc/botcommand/getin_incoming.php';

  if (function_exists('getin_incomingdata')) {
    $sendmsg = getin_incomingdata($textstr, $user, $connection);
  } else {
    $sendmsg = fnctn_help($textstr = null, $user);
  }

  return $sendmsg;
}

function getadd_out($textstr, $user, $connection)
{
  require_once 'etc/botcommand/getin_outgoing.php';

  if (function_exists('getin_expensedata')) {
    $sendmsg = getin_expensedata($textstr, $user, $connection);
  } else {
    $sendmsg = fnctn_help($textstr = null, $user);
  }

  return $sendmsg;
}

function fnctn_tr($textstr, $user)
{
  require_once 'etc/botcommand/get_transfer.php';

  if (function_exists('accounts_transfer')) {
    $connection = db_access();
    $sendmsg = accounts_transfer($textstr, $user, $connection);
  } else {
    $sendmsg = 'Oops... Something went wrong.';
  }

  return $sendmsg;
}

function fnctn_user($textstr, $user)
{
  require_once 'etc/botcommand/get_username.php';

  if (function_exists('get_lbwusername')) {
    $connection = db_access();

    $sendmsg = get_lbwusername($textstr, $user, $connection);
  } else {
    $sendmsg = 'Oops... Something went wrong.';
  }

  return $sendmsg;
}

function fnctn_about($textstr = null, $user = null)
{
  if ($textstr === 'about') {
    $sendmsg = "Website is a budget editor for the family and small business.\n";
    $sendmsg .= "Allows to create and edit a budget without having to connect to bank accounts.";
  } else {
    $sendmsg = fnctn_help($textstr, $user);
  }

  return $sendmsg;
}

function fnctn_help($textstr, $user)
{
  if ($textstr === 'help') {
    $sendmsg = "*Bot responds to commands*\n";
    $sendmsg .= "`/lbw account` Displays all accounts.\n";
    $sendmsg .= "`/lbw balance` Displays the budget summary.\n";
    $sendmsg .= "`/lbw in` Displays all incoming and the items.\n";
    $sendmsg .= "`/lbw out` Displays all outgoing and the items.\n";
    $sendmsg .= "`/lbw list in` Displays the list all `ID` names incoming.\n";
    $sendmsg .= "`/lbw list out` Displays the list all `ID` names outgoing.\n";
    $sendmsg .= "`/lbw add in X X XXXX.XX` Add incoming ID-name ID-account amount (/lbw add in 1 2 523.45).\n";
    $sendmsg .= "`/lbw add out X X XXXX.XX` Add outgoing ID-name ID-account amount (/lbw add out 3 1 84.51).\n";
    $sendmsg .= "`/lbw tr X X XXXX.XX` Transfer from ID-account to ID-account amount (/lbw tr 2 1 100.00).\n";
    $sendmsg .= "`/lbw user` Who is.\n";
    $sendmsg .= "`/lbw user -a` All users Website on the channel.\n";
    $sendmsg .= "`/lbw user -u username` Sets the user Website to the channel.\n";
    $sendmsg .= "`/lbw help` Lists available commands.\n";
    $sendmsg .= "`/lbw about` What is Website.";
  } else {
    $sendmsg = "Hello, I try to be helpful. (But I’m still just a bot. Sorry!)\nType `/lbw help` to get started.";
  }

  return $sendmsg;
}


/*
* MAIN FUNCTIONALITY
*/
# If an action was passed, execute it before rendering the page
$result_message = '';
if ( isset( $_REQUEST['action'] ) ) {
  $action = $_REQUEST['action'];

  # Setup the Slack interface
  $slack = initialize_slack_interface();

  $result_message = do_action($slack, $action);
}

/*
* Page Layout
*/
?>
<html>
    <head>
        <title>Website and Slack Integration</title>
        <style>
            body {
                font-family: Helvetica, sans-serif;
                margin: 40px;
            }
            a { color: #C9C9C9; }
            a:hover { color: #fff; }
            .bold { color: #FAFAC9; }
            li { padding: 6px; }
            .notification { padding: 20px; background-color: #4D384B; color: #C9C9C9; }
            .table_td { border: 1px solid #2E3436; padding: 8px; width: 50%; }
            .wrapper { background-color: #FFF; color: #4D384B; padding: 4px 8px 4px 8px; }
            .wrapper a { color: #4D384B; }
            .wrapper a:hover { color: #4D384B; }
        </style>
    </head>
    <body class="notification">
      <?php if ( $result_message ) : ?>
           <p class="notification"><?php echo $result_message; ?></p>
      <?php endif; ?>
        <h1><a href="Website">Website</a><br>and Slack Integration</h1>
        <br>
        <h2><span class="wrapper">Summary of what bot app does and how it integrates with Slack</span></h2>
        <ul>
        <br>
        <li><span class="bold">Access to the Slack button for the app is behind a login page, you'll need a <a href="/login/createaccount" target="_blank"><span>account</span></a> in order to use our services.</span></li>
        <li>The <a href="/notifications/slckbot" target="_blank">notifications</a> page displays the button
          <img alt="Add to Slack" height="40" width="139" src="https://platform.slack-edge.com/img/add_to_slack.png" srcset="https://platform.slack-edge.com/img/add_to_slack.png 1x, https://platform.slack-edge.com/img/add_to_slack@2x.png 2x"> for users to install the app.
        </li>
        <li>If you encounter problems or need help, please contact to our team on <a href="mailto:">mail</a></li>
        </ul>
        <br>
        <h2><span class="wrapper">Bot responds to commands</span></h2>
        <table style="width:50%; margin-left: 20px">
          <tr>
            <th>Command</th><th>Example Response</th>
          </tr>
          <tr>
            <td><span class="bold">/lbw account</span><br><br>Displays the accounts.</td>
            <td class="table_td">
            <b>Account Summary</b><br>
        		name R2-D2<br>
        		<b>Cash</b><br>
        		<span class="bold">1</span> wallet R2-D2 <b>0.00</b><br>
        		<span class="bold">2</span> wallet BB-8 <b>3.00</b><br>
        		<b>Accounts</b><br>
        		<span class="bold">3</span> Cheking 7979 <b>1,130.00</b><br>
        		<span class="bold">4</span> Savings 8383 <b>300.04</b><br>
        		<span class="bold">5</span> Liquid 9292 <b>12.62</b><br>
        		<span class="bold">6</span> Credit_R2-D2 5995 <b>0.00</b><br>
        		<span class="bold">7</span> Credit_BB-8 9696 <b>0.00</b><br>
        		<?php echo date('M d Y');?>
          </td>
          </tr>
          <tr>
            <td><span class="bold">/lbw balance</span><br><br>Displays the budget summary.</td>
            <td class="table_td">
            <b>Balance Summary</b><br>
        		name R2-D2<br>
        		index <b>6.70</b><br>
        		balance in/out <b>1,445.66</b><br>
        		incoming <b>215.78</b><br>
        		outgoing <b>270.36</b><br>
        		balance as/li <b>7.09</b><br>
        		assets <b>7.09</b><br>
        		liabilities <b>0.00</b><br>
            <?php echo date('M d Y');?>
            </td>
          </tr>
          <tr>
            <td><span class="bold">/lbw in</span><br><br>Displays all incoming and the items.</td>
            <td class="table_td">
            <b>Incoming</b><br>
        		name R2-D2<br>
        		total <b>215.78</b><br>
        		money <b>215.78</b><br>
        		<?php echo date('M d Y');?>
            </td>
          </tr>
          <tr>
            <td><span class="bold">/lbw out</span><br><br>Displays all outgoing and the items.</td>
            <td class="table_td">
            <b>Outgoing</b><br>
        		name R2-D2<br>
        		total <b>270.36</b><br>
        		food/cafe <b>90.48</b><br>
        		personal care <b>134.00</b><br>
        		gifts and donations <b>45.88</b><br>
            <?php echo date('M d Y');?>
            </td>
          </tr>
          <tr>
            <td><span class="bold">/lbw list in</span></td>
            <td class="table_td"><br>Displays the list all <span class="bold">ID</span> names incoming.<br><br></td>
          </tr>
          <tr>
            <td><span class="bold">/lbw list out</span></td>
            <td class="table_td"><br>Displays the list all <span class="bold">ID</span> names outgoing.<br><br></td>
          </tr>
          <tr>
            <td><span class="bold">/lbw add in X X XXXX.XX</span></td>
            <td class="table_td"><br>Add incoming<br>ID-name ID-account amount<br><span class="bold">/lbw add in 1 2 523.45</span><br><br></td>
          </tr>
          <tr>
            <td><span class="bold">/lbw add out X X XXXX.XX</span></td>
            <td class="table_td"><br>Add outgoing<br>ID-name ID-account amount<br><span class="bold">/lbw add out 3 1 84.51</span><br><br></td>
          </tr>
          <tr>
            <td><span class="bold">/lbw tr X Y XXXX.XX</span></td>
            <td class="table_td"><br>Transfer<br>from ID-account to ID-account<br>amount<br><span class="bold">/lbw tr 2 1 100.00</span><br><br></td>
          </tr>
          <tr>
            <td><span class="bold">/lbw user</span></td><td class="table_td">Who is.</td>
          </tr>
          <tr>
            <td><span class="bold">/lbw user -a</span></td><td class="table_td">All users Website on the channel.</td>
          </tr>
          <tr>
            <td><span class="bold">/lbw user -u username</span></td><td class="table_td">Sets the user Website to the channel.</td>
          </tr>
          <tr>
            <td><span class="bold">/lbw help</span></td><td class="table_td">Lists available commands.</td>
          </tr>
          <tr>
            <td><span class="bold">/lbw about</span></td><td class="table_td">What is Website.</td>
          </tr>
        </table>
        <br>
        <h2><span class="wrapper"><a name="PP">Privacy policy</a></span></h2>
        <ul>
        <li>Website and bot app are not using any third-party data.</li>
        <li>Website is a budget editor without having to connect to bank accounts.</li>
        <li>Our Services allow you to submit, store, send or receive content.<br>
          You retain ownership of any intellectual property rights that you hold in that content.<br>
          In short, what belongs to you stays yours.</li>
        <li>Website not share your data with third parties, and user data is not sold.</li>
        </ul>
    </body>
</html>
