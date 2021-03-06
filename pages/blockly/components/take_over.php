<?php
  use \system\packages\duckietown_duckiebot\Duckiebot;
  use \system\packages\ros\ROS;
?>

<span style="float: right; font-size: 12pt">Take over&nbsp;
  <input type="checkbox" data-toggle="toggle" data-onstyle="primary" data-offstyle="warning" data-class="fast" data-size="small" name="vehicle_driving_mode_toggle" id="vehicle_driving_mode_toggle">
</span>

<?php
  // TODO: get these from ROS param
  $v_gain = 0.5;
  $omega_gain = 8.3;
  $sensitivity = 0.5;
  $output_commands_hz = 10.0;
  $vehicle_name = Duckiebot::getDuckiebotName();

  // apply sensitivity
  $omega_gain *= $sensitivity;
  ROS::connect();
  console_log("[DEBUG] Obatined Duckiebot is: " . $vehicle_name);
  console_log("[INFO] Is ROS Initialized? " . (ROS::isInitialized() ? 'Yes' : 'NO!'))
?>


<script type="text/javascript">
  //! Setup ROS resource for non blockly page. Comment this section out for blockly
  console.log("[INFO] Is ROS Initialized? " . (ROS::isInitialized() ? 'Yes' : 'NO!'))
  function to_update_ros_status(event) {
        window.to_ros_resources = {
          to_estop: {
            topic_name: '/<?php echo $vehicle_name ?>/wheels_driver_node/emergency_stop',
            messageType: 'duckietown_msgs/BoolStamped',
            queue_size: 1,
            frequency: 10
          },
          to_commands: {
            topic_name: '/<?php echo $vehicle_name ?>/joy_mapper_node/car_cmd',
            messageType: 'duckietown_msgs/Twist2DStamped',
            queue_size: 1,
            frequency: 10
          }
        };
        to_advertise = ["to_estop","to_commands"];
        for (var i in to_advertise) {
            window.ROSDB.advertise(
                to_advertise[i],
                window.to_ros_resources[to_advertise[i]]['topic_name'],
                window.to_ros_resources[to_advertise[i]]['messageType'],
                window.to_ros_resources[to_advertise[i]]['frequency'],
                window.to_ros_resources[to_advertise[i]]['queue_size']
            );
        }
        window.to_blockly_provides = provides;
    } //update_ros_status

  function to_update_data_status() {
      resources_list = [
          window.to_blockly_provides
      ];
      hz_0_colors = [
          'red',
          'black'
      ];
      for (var j in resources_list) {
          var resources = resources_list[j];
          var color = hz_0_colors[j];
          for (var i in resources) {
              var resource_name = resources[i];
              var expected_hz = window.ros_resources[resource_name]['frequency'];
              var elem = $('#{0}-data-source-status'.format(resource_name));
              var hz = window.ROSDB.hz(resource_name);
              if (hz >= 0.6 * expected_hz)
                  color = 'green';
              if (hz > 0.4 * expected_hz && hz < 0.6 * expected_hz)
                  color = 'orange';
              elem.css('color', color);
              elem.prop('title', '{0} Hz'.format(hz.toFixed(2)));
          }
      }
  } //update_data_status
  to_update_ros_status()
  setInterval(to_update_data_status, 100);

  // estop toggle switch control
  window.estopSet = true;
  var toggleEstop = function(){
    var on = false;
    return function(){
      if(!on){
        on = true;
        window.estopSet = true; //switch on estop on
        window.ROSDB.publish('estop',{data:true})
        window.ROSDB.publish('estop',{data:true})
        window.ROSDB.publish('estop',{data:true})
        $('body').css('background-image', 'linear-gradient(to top, #F7F7F6, #ff0000, #F7F7F6)');
        $('#vehicle_driving_mode_status').html('ESTOPPED!');
        return;
      }
      window.estopSet = false; //switch off estop off
      window.ROSDB.publish('estop',{data:false})
      window.ROSDB.publish('estop',{data:false})
      window.ROSDB.publish('estop',{data:false})
      on = false;
      if (window.mission_control_Mode =='manual'){
        $('body').css('background-image', 'linear-gradient(to top, #F7F7F6, #FFC800, #F7F7F6)');
        $('#vehicle_driving_mode_status').html('Manual');
      }else{
        $('body').css('background-image', 'linear-gradient(to top, #F7F7F6, #00ff00, #F7F7F6)');
        $('#vehicle_driving_mode_status').html('Auto');
      }
    }
  }();
  toggleEstop(); //Defult estop off


  // define the list of keys that can be used to drive the vehicle
  window.mission_control_Keys = {
    UP_ARROW: 38,
    LEFT_ARROW: 37,
    DOWN_ARROW: 40,
    RIGHT_ARROW: 39,
  };

  // define buffer of pressed keys
  window.mission_control_keyMap = {};
  // set all the keys to False (i.e., not-pressed)
  for (let item in window.mission_control_Keys) {
    if (isNaN(Number(item))) {
      window.mission_control_keyMap[window.mission_control_Keys[item]] = false;
    }
  }

  // capture keyboard events (and update buffer accordingly)
  function key_cb(e) {
    if (window.mission_control_Mode != 'manual')
      return;
    if (window.estopSet == true){
      console.log("[WARNING] Attempted manual drive when estoped!")
      return;
    }
    // space and arrow keys
    if ([37, 38, 39, 40].indexOf(e.keyCode) > -1) {
      e.preventDefault();
      window.mission_control_keyMap[e.keyCode] = e.type == "keydown";
    }
  } //key_cb

  // define the callback function that turns the key_map into a ROS message
  function publish_command() {
    // when toggle switch is off
    if (window.mission_control_Mode != 'manual')
      return;
    //TODO: Check for estop:
    keys = window.mission_control_Keys;
    key_map = window.mission_control_keyMap;
    // compute linear/angular speeds
    v_gain = <?php echo $v_gain ?>;
    omega_gain = <?php echo $omega_gain ?>;
    v_val = Math.min(key_map[keys.UP_ARROW], 1) - Math.min(key_map[keys.DOWN_ARROW], 1);
    omega_val = Math.min(key_map[keys.LEFT_ARROW], 1) - Math.min(key_map[keys.RIGHT_ARROW], 1);
    // create a message
    var car_cmd = new ROSLIB.Message({
      v: v_val * v_gain,
      omega: omega_val * omega_gain
    });
    window.ROSDB.publish('commands',car_cmd)
  } //publish_command

  // publish command at regular rate
  $(document).on('<?php echo ROS::get_event(ROS::$ROSBRIDGE_CONNECTED) ?>', function(e) {

    // attach listeners to key events
    window.addEventListener("keyup", key_cb, false);
    window.addEventListener("keydown", key_cb, false);
    window.addEventListener("keydown",function(e) {
      var key = e.keyCode || e.which;
      if(key === 32) {
        toggleEstop();
      }
    }, false);
    // start publishing commands to the vehicle
    setInterval(publish_command, <?php echo intval(1000.0 / $output_commands_hz) ?>);
  });

  $(document).ready(function() {
    window.mission_control_Mode = 'autonomous';
  });

  $('#vehicle_driving_mode_toggle').change(function() {
    if ($(this).prop('checked')) {
      // change the page background only when estop is lifted:
      if (window.estopSet!=true){
        $('body').css('background-image', 'linear-gradient(to top, #F7F7F6, #FFC800, #F7F7F6)');
        $('#vehicle_driving_mode_status').html('Manual');
        window.mission_control_Mode = 'manual';
      }else{
        $('body').css('background-image', 'linear-gradient(to top, #F7F7F6, #ff0000, #F7F7F6)');
        $('#vehicle_driving_mode_status').html('ESTOPPED!');
        window.mission_control_Mode = 'manual';
      }
    }else {
      window.ROSDB.publish('estop',{data:true});
      window.ROSDB.publish('estop',{data:true});
      window.ROSDB.publish('estop',{data:true});
      window.mission_control_Mode = 'ESTOPPED!';
      window.estopSet=true;
      if (window.estopSet!=true){
        $('body').css('background-image', 'linear-gradient(to top, #F7F7F6, #00ff00, #F7F7F6)');
        $('#vehicle_driving_mode_status').html('Auto');
      }else{
        $('body').css('background-image', 'linear-gradient(to top, #F7F7F6, #ff0000, #F7F7F6)');
        $('#vehicle_driving_mode_status').html('ESTOPPED!');
      }

      window.mission_control_Mode = 'autonomous';
    }
  });
</script>