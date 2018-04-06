<?php
# A class for holding the Slack access information and passing it to the application using the interface.
namespace Slack_Interface;

/*
* A class for holding Slack authentication data.
*/
class Slack_Access {
  # Slack OAuth data
  private $team_id;
  private $channel_id;
  private $user_lbw;
  public function __construct( $data ) {
    $this->team_id = isset( $data['team_id'] ) ? $data['team_id'] : '';
    $this->channel_id = isset( $data['channel_id'] ) ? $data['channel_id'] : '';
    $this->user_lbw = isset( $data['user_lbw'] ) ? $data['user_lbw'] : array();
  }

/*
 * Returns the team, channel, user lbw to which the user has authorized the application
 * to post notifications.
 *
 * @return string   The selected Slack team, channel's ID
 */
public function get_teamid() {
  if(isset($this->team_id)) {
    return $this->team_id;
  }
  return '';
}

public function get_channelid() {
  if(isset($this->channel_id)) {
    return $this->channel_id;
  }
  return '';
}
/*
* @return array
*/
public function get_user() {
  if(is_array($this->user_lbw) && isset($this->user_lbw)) {
    return $this->user_lbw;
  }
  return '';
}

}
