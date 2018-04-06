<?php
# The Slack interface class
namespace Slack_Interface;

use Requests;

/*
* A basic Slack interface you can use as a starting point
* for your own Slack projects.
*/
class Slack {

  private static $api_root = 'https://slack.com/api/';

/*
* @var Slack_Access Slack authorization data
*/
  private $access;

/*
* @var array $slash_commands   An associative array of slash commands
* attached to this Slack interface
*/
  private $slash_commands;

/*
* Sets up the Slack interface object.
*
* @param array $access_data An associative array containing OAuth authentication information.
* If the user is not yet authenticated, pass an empty array.
*/
  public function __construct( $access_data ) {
    if ( $access_data ) {
        $this->access = new Slack_Access( $access_data );
    }
    $this->slash_commands = array();
  }

/*
* Registers a new slash command to be available through this
* Slack interface.
*
* @param string    $command    The slash command
* @param callback  $callback   The function to call to execute the command
*/
  public function register_slash_command( $command, $callback ) {
      $this->slash_commands[$command] = $callback;
  }

  /**
   * Runs a slash command passed in the $_POST data if the
   * command is valid and has been registered using register_slash_command.
   *
   * The response written by the function will be read by Slack.
   */
  public function do_slash_command() {
      # Collect request parameters
      $token      = isset( $_POST['token'] ) ? $_POST['token'] : '';
      $command    = isset( $_POST['command'] ) ? $_POST['command'] : '';
      $text       = isset( $_POST['text'] ) ? $_POST['text'] : '';
      $team_id  = isset( $_POST['team_id'] ) ? $_POST['team_id'] : '';
      $channel_id  = isset( $_POST['channel_id'] ) ? $_POST['channel_id'] : '';

      # Use the command verification token to verify the request
      if ( ! empty( $token ) && $this->get_command_token() == $_POST['token'] ) {
          header( 'Content-Type: application/json' );

          if ( isset( $this->slash_commands[$command] ) ) {
              # This slash command exists, call the callback function to handle the command
              if ($channel_id == $this->access->get_channelid()) {
                # find uid
                $user = $this->access->get_user();
                $response = call_user_func($this->slash_commands[$command], $text, $user);
              } else {
                $response = array(
                  'response_type' => 'ephemeral',
                  'text' => 'Got it!',
                );
              }
              echo json_encode( $response );
          } else {
              # Unknown slash command
              echo json_encode( array(
                  'response_type' => 'ephemeral',
                  'text' => "Sorry, I don't know how to respond to the command."
              ) );
          }
      } else {
          echo json_encode( array(
            'response_type' => 'ephemeral',
            'text' => 'Oops... Something went wrong.'
          ) );
      }

      # Don't print anything after the response
      exit;
  }

/*
* Returns the Slack client ID.
*
* @return string The client ID or empty string if not configured
*/
  public function get_client_id() {
    # look for environment variable
    if(getenv('SLACK_CLIENT_ID')) {
        return getenv('SLACK_CLIENT_ID');
    }
    # Not configured, return empty string
    return '';
  }

/*
* Returns the Slack client secret.
* @return string   The client secret or empty string if not configured
*/
  private function get_client_secret() {
    # look for environment variable
    if(getenv('SLACK_CLIENT_SECRET')) {
      return getenv('SLACK_CLIENT_SECRET');
    }
    # Not configured, return empty string
    return '';
  }

/*
* Returns the command verification token.
* @return string The command verification token or empty string if not configured
*/
  private function get_command_token() {
      # look for environment variable
      if(getenv('SLACK_COMMAND_TOKEN')) {
          return userid__decrypt(getenv('SLACK_COMMAND_TOKEN'), ENV_PRIVATE_KEY);
      }
      # Not configured, return empty string
      return '';
  }
}
