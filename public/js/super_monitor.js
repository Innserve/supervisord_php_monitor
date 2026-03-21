$(init_super);

let play_pause_timeout;
const VERSION_CHECK_DUE_KEY = 'time_check_due';
const VERSION_CHECK_INTERVAL_MS = 6 * 60 * 60 * 1000;
const PANEL_STATE_KEY = 'super_monitor_expanded_panels';

function init_super() {
  initialise_refresh_countdown();
  initialise_confirmation_prompts();
  initialise_server_toggles();
  $('#start_stop_refresh').click( super_play_pause );
}

function initialise_confirmation_prompts() {
  $('.confirmable-form').submit( function(event) {
    const confirmationMessage = $(this).attr('data-confirm-message');

    if( confirmationMessage && !window.confirm(confirmationMessage) ) {
      event.preventDefault();
    }
  } );
}

function toggle_server_panel( $button ) {
  const target = $button.data('target');
  const isExpanded = $button.attr('aria-expanded') === 'true';

  set_server_panel_state(target, !isExpanded);
}

function set_server_panel_state( target, isExpanded, persistState = true ) {
  const $panel = $('#' + target);

  if( $panel.length === 0 ) {
    return;
  }

  const expandedValue = isExpanded ? 'true' : 'false';
  $panel.prop('hidden', !isExpanded);
  $('.server-toggle[data-target="' + target + '"], .server-panel-summary[data-target="' + target + '"]')
    .attr('aria-expanded', expandedValue);
  $('.server-toggle[data-target="' + target + '"]').html(
    isExpanded
      ? '<i class="bi bi-chevron-up"></i> Collapse'
      : '<i class="bi bi-chevron-down"></i> Expand'
  );

  if( persistState ) {
    persist_server_panel_state(target, isExpanded);
  }
}

function get_saved_server_panel_states() {
  try {
    const savedState = localStorage.getItem(PANEL_STATE_KEY);

    if( savedState === null ) {
      return {};
    }

    const parsedState = JSON.parse(savedState);
    return typeof parsedState === 'object' && parsedState !== null ? parsedState : {};
  }
  catch( _error ) {
    return {};
  }
}

function persist_server_panel_state( target, isExpanded ) {
  const savedState = get_saved_server_panel_states();

  if( isExpanded ) {
    savedState[target] = true;
  }
  else {
    delete savedState[target];
  }

  try {
    localStorage.setItem(PANEL_STATE_KEY, JSON.stringify(savedState));
  }
  catch( _error ) {
  }
}

function restore_server_panel_states() {
  const savedState = get_saved_server_panel_states();

  $('.server-toggle').each( function() {
    const target = $(this).data('target');
    set_server_panel_state(target, savedState[target] === true, false);
  } );
}

function initialise_server_toggles() {
  $('.server-toggle, .server-panel-summary').click( function() {
    toggle_server_panel($(this));
  } );

  restore_server_panel_states();
}

function check_version() {
  let should_i_check_for_updates = true; // assume so

  const time_now = new Date().getTime();
  const time_check_due = get_version_check_due_ts();

  if( time_check_due !== null && time_now < time_check_due ) {
    console.log( Math.round((time_check_due-time_now)/1000/60) + " minutes until I next check versions");
    should_i_check_for_updates = false;
  }

  if( should_i_check_for_updates ) {
    const tagsUrl = $('#version_badge').data('tags-url');
    if( !tagsUrl ) {
      handle_upgrade_results(false, false, '', 'Version check URL missing');
      return;
    }

    const options = {
      url: tagsUrl,
      cache: false,
      dataType: 'json',
      timeout: 5000,
    };

    let version_promise = $.ajax(options);
    version_promise.done( got_version_check );
    version_promise.fail( function(_jqXHR, textStatus) {
      handle_upgrade_results(false, false, '', 'Version check failed: ' + textStatus);
    } );
  }
  else {
    handle_upgrade_results(false, false, '');
  }
}

function got_version_check( response ) {
  if( !Array.isArray(response) || response.length === 0 || !response[0].name ) {
    handle_upgrade_results(false, false, '', 'Version check returned no tags');
    return;
  }

  const latest_version = response[0].name;
  let upgrade_results = should_i_upgrade( $('#gh_version_number').text(), latest_version );
  handle_upgrade_results(upgrade_results, true, latest_version);
}

function handle_upgrade_results( should_i_upgrade_result, cache_results, latest_version, errorMessage ) {
  if( errorMessage ) {
    $('#version_badge')
      .addClass('text-muted')
      .attr('title', errorMessage)
      .html('<i class="bi bi-exclamation-circle"></i>');
    console.warn(errorMessage);
    return;
  }

  if( should_i_upgrade_result ){
    $('#version_badge').addClass('text-danger').html( '<i class="bi bi-x-circle-fill"></i> Update available: '+latest_version );
  } else {
    $('#version_badge').addClass('text-success').html( '<i class="bi bi-check-circle-fill"></i>' );
    if( cache_results ) { // we only set if we didn't skip the check
      set_version_check_due_ts();
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
    const a = Number(newParts[i] || 0);
    const b = Number(oldParts[i] || 0);

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

function get_version_check_due_ts() {
  const value = localStorage.getItem(VERSION_CHECK_DUE_KEY);
  return value === null ? null : Number(value);
}

function set_version_check_due_ts() {
  const time_now = new Date().getTime();
  localStorage.setItem(VERSION_CHECK_DUE_KEY, time_now + VERSION_CHECK_INTERVAL_MS);
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
