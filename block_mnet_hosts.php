<?php

class block_mnet_hosts extends block_list {
    function init() {
        $this->title = get_string('pluginname','block_mnet_hosts') ;
        $this->version = 2007101509;
    }

    function has_config() {
        return false;
    }

    function applicable_formats() {
        if (has_capability('moodle/site:mnetlogintoremote', get_context_instance(CONTEXT_SYSTEM), NULL, false)) {
            return array('all' => true, 'mod' => false, 'tag' => false);
        } else {
            return array('site' => true);
        }
    }

    function get_content() {
        global $CFG, $USER, $DB, $OUTPUT;

        // shortcut -  only for logged in users!
        if (!isloggedin() || isguestuser()) {
            return false;
        }

        // according to start_jump_session,
        // remote users can't on-jump
        // so don't show this block to them
        if (is_mnet_remote_user($USER)) {
            return '';
        }

        if (!is_enabled_auth('mnet')) {
            // no need to query anything remote related
            debugging( 'mnet authentication plugin is not enabled', DEBUG_ALL );
            return '';
        }

        // check for outgoing roaming permission first
        if (!has_capability('moodle/site:mnetlogintoremote', get_context_instance(CONTEXT_SYSTEM), NULL, false)) {
            return '';
        }

        if ($this->content !== NULL) {
            return $this->content;
        }

        // TODO: Test this query - it's appropriate? It works?
        // get the hosts and whether we are doing SSO with them
        $sql = "
             SELECT DISTINCT
                 h.id,
                 h.name,
                 h.wwwroot,
                 a.name as application,
                 a.display_name
             FROM
                 {mnet_host} h,
                 {mnet_application} a,
                 {mnet_host2service} h2s_IDP,
                 {mnet_service} s_IDP,
                 {mnet_host2service} h2s_SP,
                 {mnet_service} s_SP
             WHERE
                 h.id <> ? AND
                 h.id = h2s_IDP.hostid AND
                 h.deleted = 0 AND
                 h.applicationid = a.id AND
                 h2s_IDP.serviceid = s_IDP.id AND
                 s_IDP.name = 'sso_idp' AND
                 h2s_IDP.publish = '1' AND
                 h.id = h2s_SP.hostid AND
                 h2s_SP.serviceid = s_SP.id AND
                 s_SP.name = 'sso_idp' AND
                 h2s_SP.publish = '1'
             ORDER BY
                 a.display_name,
                 h.name";

        $hosts = $DB->get_records_sql($sql, array($CFG->mnet_localhost_id));

        $this->content = new stdClass;
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        if ($hosts) {
            foreach ($hosts as $host) {
            $icon  = '<img src="'.$OUTPUT->pix_url('i/'.$host->application.'_host') . '"'.
                ' class="icon" alt="'.get_string('server', 'block_mnet_hosts').'" />';

                $this->content->icons[]=$icon;
                if ($host->id == $USER->mnethostid) {
                    $this->content->items[]="<a title=\"" .s($host->name).
                        "\" href=\"{$host->wwwroot}\">". s($host->name) ."</a>";
                } else {
                    $this->content->items[]="<a title=\"" .s($host->name).
                        "\" href=\"{$CFG->wwwroot}/auth/mnet/jump.php?hostid={$host->id}\">" . s($host->name) ."</a>";
                }
            }
        }

        return $this->content;
    }
}


