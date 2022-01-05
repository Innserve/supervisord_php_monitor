$(init_super);

let play_pause_timeout;

function init_super() {
  initialise_refresh_countdown();
  check_version();
  $('#start_stop_refresh').click( super_play_pause );
}

function check_version() {
  let should_i_check_for_updates = true; // assume so

  const time_now = new Date().getTime();
  const time_check_due = localStorage.getItem('time_check_due');

  if( time_check_due !== null && time_now < time_check_due ) {
    console.log( Math.round((time_check_due-time_now)/1000/60) + " minutes until I next check versions");
    should_i_check_for_updates = false;
  }

  if( should_i_check_for_updates ) {
    const options = {
      url: "https://api.github.com/repos/Innserve/supervisord_php_monitor/tags",
      cache: false,
      dataType: 'json',
    };

    let version_promise = $.ajax(options);
    version_promise.done( got_version_check );
  }
  else {
    handle_upgrade_results(false, false, '');
  }
}

function got_version_check( response ) {
  const latest_version = response[0].name;
  let upgrade_results = should_i_upgrade( $('#gh_version_number').text(), latest_version );
  handle_upgrade_results(upgrade_results, true, latest_version);
}

function handle_upgrade_results( should_i_upgrade_result, cache_results, latest_version ) {
  if( should_i_upgrade_result ){
    $('#version_badge').addClass('text-danger').html( '<i class="bi bi-x-circle-fill"></i> Update available: '+latest_version );
  } else {
    $('#version_badge').addClass('text-success').html( '<i class="bi bi-check-circle-fill"></i>' );
    if( cache_results ) { // we only set if we didn't skip the check
      const time_now = new Date().getTime();
      const time_next_check = time_now + (6*60*60*1000); // 6 hours
      localStorage.setItem('time_check_due', time_next_check);
    }
  }
}

function should_i_upgrade ( oldVer, newVer ) {
  oldVer = oldVer.replace(/[^0-9.]/g, '').trim();
  newVer = newVer.replace(/[^0-9.]/g, '').trim();

  if(oldVer === newVer) {
    console.log("Version check: version is current");
    return false;
  }

  const oldParts = oldVer.split('.');
  const newParts = newVer.split('.');
  for (var i = 0; i < newParts.length; i++) {
    const a = ~~newParts[i];
    const b = ~~oldParts[i];

    if (a > b) {
      console.log("Version check: you are behind latest, update now");
      return true;
    }
    if (a < b) {
      console.log("Version check: you are ahead of latest");
      return false;
    }
  }

  return false;
}

function initialise_refresh_countdown() {
  const $refresh_count_dom = $('#refresh_count');
  let refresh_count = $refresh_count_dom.text()*1;
  if( refresh_count === 0 ) {
    $refresh_count_dom.parent().html("<small class='text-muted'>Auto refreshing is disabled in your config</small>");
    return; // Started at 0, never run again
  }

  refresh_count--;

  if( refresh_count === 0 ) {
    location.reload();
  }
  else {
    $refresh_count_dom.text(refresh_count);
    play_pause_timeout = setTimeout(initialise_refresh_countdown, 1000);
  }
}

function super_play_pause() {
  const $start_stop_refresh_btn = $('#start_stop_refresh');

  if( $start_stop_refresh_btn.hasClass('bi-pause-circle') ){ // pause countdown
    clearTimeout(play_pause_timeout);
    $start_stop_refresh_btn.removeClass('bi-pause-circle');
    $start_stop_refresh_btn.addClass('bi-play-circle');
    $start_stop_refresh_btn.parent().addClass('text-muted');
  }
  else{ // restart countdown
    $start_stop_refresh_btn.removeClass('bi-play-circle');
    $start_stop_refresh_btn.addClass('bi-pause-circle');
    $start_stop_refresh_btn.parent().removeClass('text-muted');
    play_pause_timeout = setTimeout(initialise_refresh_countdown, 1000);
  }
}
