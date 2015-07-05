<?php

require( 'auth.php' );

OpenLibrary( 'hui.library' );
OpenLibrary( 'ampcentral-client.library' );
OpenLibrary( 'locale.library' );
OpenLibrary( 'ampshared.library' );

$gHui = new Hui( $gEnv['root']['db'] );
$gHui->LoadWidget( 'xml' );
$gHui->LoadWidget( 'amppage' );
$gHui->LoadWidget( 'amptoolbar' );

$gLocale = new Locale( 
    'ampcentral-client_root_client', 
    $gEnv['root']['locale']['language'] );

$gPage_content = $gStatus = $gToolbars = $gXml_def = '';
$gPage_title = $gLocale->GetStr( 'ampcentral-client.title' );

$gMenu = get_ampoliros_root_menu_def( $gEnv['root']['locale']['language'] );

$gToolbars['repository'] = array(
    'repository' => array(
        'label' => $gLocale->GetStr( 'repository.toolbar' ),
        'themeimage' => 'view_text',
        'action' => build_events_call_string( '', array( array( 'main', 'default', '' ) ) ),
        'horiz' => 'true'
        ),
    'newrepository' => array(
        'label' => $gLocale->GetStr( 'newrepository.toolbar' ),
        'themeimage' => 'filenew',
        'action' => build_events_call_string( '', array( array( 'main', 'newrepository', '' ) ) ),
        'horiz' => 'true'
        )
    );

// Action dispatcher
//
$gAction_disp = new HuiDispatcher( 'action' );

$gAction_disp->AddEvent( 'newrepository', 'action_newrepository' );
function action_newrepository( $eventData )
{
    global $gEnv, $gLocale, $gStatus;

    $remote_ac = new AmpCentralRemoteServer( $gEnv['root']['db'] );
    if ( $remote_ac->Add( $eventData['accountid'] ) ) $gStatus = $gLocale->GetStr( 'repository_added.status' );
    else $gStatus = $gLocale->GetStr( 'repository_not_added.status' );
}

$gAction_disp->AddEvent( 'removerepository', 'action_removerepository' );
function action_removerepository( $eventData )
{
    global $gEnv, $gLocale, $gStatus;

    $remote_ac = new AmpCentralRemoteServer( $gEnv['root']['db'], $eventData['id'] );
    if ( $remote_ac->Remove() ) $gStatus = $gLocale->GetStr( 'repository_removed.status' );
    else $gStatus = $gLocale->GetStr( 'repository_not_removed.status' );
}

$gAction_disp->AddEvent( 'installmodule', 'action_installmodule' );
function action_installmodule( $eventData )
{
    global $gEnv, $gLocale, $gStatus;

    $remote_ac = new AmpCentralRemoteServer( $gEnv['root']['db'], $eventData['id'] );
    if ( $remote_ac->RetrieveModule(
        $eventData['repid'],
        $eventData['moduleid'],
        isset( $eventData['version'] ) ? $eventData['version'] : '' ) )
        $gStatus = $gLocale->GetStr( 'module_installed.status' );
    else $gStatus = $gLocale->GetStr( 'module_not_installed.status' );
}

$gAction_disp->Dispatch();

// Main dispatcher
//
$gMain_disp = new HuiDispatcher( 'main' );

function reps_tab_action_builder( $tab )
{
    return build_events_call_string( '', array( array(
        'main', 'default', array( 'activetab' => $tab
        ) ) ) );
}

$gMain_disp->AddEvent( 'default', 'main_default' );
function main_default( $eventData )
{
    global $gEnv, $gLocale, $gXml_def, $gPage_title, $gStatus, $gToolbars;

    $reps_query = &$gEnv['root']['db']->Execute( 'SELECT ampcentralremotereps.id AS id,ampcentralremotereps.accountid AS accountid, xmlrpcaccounts.name AS name '.
        'FROM ampcentralremotereps,xmlrpcaccounts '.
        'WHERE ampcentralremotereps.accountid=xmlrpcaccounts.id '.
        'ORDER BY name' );

    if ( $reps_query->NumRows() )
    {
        $tabs = array();

        while ( !$reps_query->eof )
        {
            $tabs[]['label'] = $reps_query->Fields( 'name' );
            $reps_query->MoveNext();
        }

        $headers[0]['label'] = $gLocale->GetStr( 'repository_name.header' );

        $gXml_def =
'<vertgroup><name>reps</name>
  <children>
    <tab><name>repositories</name>
      <args>
        <tabs type="array">'.huixml_encode( $tabs ).'</tabs>
        <tabactionfunction>reps_tab_action_builder</tabactionfunction>
        <activetab>'.( isset( $eventData['activetab'] ) ? $eventData['activetab'] : '' ).'</activetab>
      </args>
      <children>';

        $reps_query->MoveFirst();
        while ( !$reps_query->eof )
        {
            $ac_remote = new AmpCentralRemoteServer( $gEnv['root']['db'], $reps_query->Fields( 'id' ) );
            $avail_reps = $ac_remote->ListAvailableRepositories( isset( $eventData['refresh'] ) ? true : false );

            $gXml_def .=
'<vertgroup><name>tab</name><children>
<table><name>reps</name>
  <args>
    <headers type="array">'.huixml_encode( $headers ).'</headers>
  </args>
  <children>';

            $row = 0;

            while ( list( $id, $data ) = each( $avail_reps ) )
            {
                $gXml_def .=
'<label row="'.$row.'" col="0"><name>rep</name>
  <args>
    <label type="encoded">'.urlencode( '<strong>'.$data['name'].'</strong><br>'.$data['description'] ).'</label>
  </args>
</label>
<amptoolbar row="'.$row.'" col="1"><name>tb</name>
  <args>
    <frame>false</frame>
    <toolbars type="array">'.huixml_encode( array(
        'main' => array(
            'modules' => array(
                'label' => $gLocale->GetStr( 'repository_modules.button' ),
                'themeimage' => 'view_detailed',
                'horiz' => 'true',
                'action' => build_events_call_string( '', array(
                    array( 'main', 'repositorymodules', array( 'id' => $reps_query->Fields( 'id' ), 'repid' => $id ) )
                    ) ) ) ) ) ).'</toolbars>
  </args>
</amptoolbar>';
                $row++;
            }

            $gXml_def .=
'  </children>
</table>
        <horizbar><name>hb</name></horizbar>
         <button><name>remove</name>
           <args>
             <themeimage>edittrash</themeimage>
             <horiz>true</horiz>
             <frame>false</frame>
             <action type="encoded">'.urlencode( build_events_call_string( '', array(
                array( 'main', 'default', '' ),
                array( 'action', 'removerepository', array( 'id' => $reps_query->Fields( 'id' ) ) )
                ) ) ).'</action>
             <label type="encoded">'.urlencode( $gLocale->GetStr( 'remove_account.button' ) ).'</label>
             <needconfirm>true</needconfirm>
             <confirmmessage type="encoded">'.urlencode( $gLocale->GetStr( 'remove_account.confirm' ) ).'</confirmmessage>
           </args>
         </button>
</children></vertgroup>';

            $reps_query->MoveNext();
        }

      $gXml_def .=
'
      </children>
    </tab>
  </children>
</vertgroup>';

                $gToolbars['reptools'] = array(
				    'refresh' => array(
        				'label' => $gLocale->GetStr( 'refresh.button' ),
				        'themeimage' => 'reload',
                        'horiz' => 'true',
				        'action' => build_events_call_string( '', array( array( 'main', 'default', array( 'refresh' => '1' ) ) ) )
                    ) );

    }
    else
    {
        if ( !strlen( $gStatus ) ) $gStatus = $gLocale->GetStr( 'no_repositories.status' );
    }

    $gPage_title .= ' - '.$gLocale->GetStr( 'repositories.title' );
}

$gMain_disp->AddEvent( 'newrepository', 'main_newrepository' );
function main_newrepository( $eventData )
{
    global $gEnv, $gLocale, $gXml_def, $gPage_title;

    $accs_query = &$gEnv['root']['db']->Execute( 'SELECT id,name '.
        'FROM xmlrpcaccounts '.
        'ORDER BY name' );

    $accounts = array();

    while ( !$accs_query->eof )
    {
        $accounts[$accs_query->Fields( 'id' )] = $accs_query->Fields( 'name' );
        $accs_query->MoveNext();
    }

    $gXml_def =
'<vertgroup><name>new</name>
  <children>
    <label><name>newrep</name>
      <args>
        <label type="encoded">'.urlencode( $gLocale->GetStr( 'newrepository.title' ) ).'</label>
        <bold>true</bold>
      </args>
    </label>
    <form><name>newrepository</name>
      <args>
        <method>post</method>
        <action type="encoded">'.urlencode( build_events_call_string( '', array(
            array( 'main', 'default', '' ),
            array( 'action', 'newrepository', '' )
            ) ) ).'</action>
      </args>
      <children>
        <grid><name>new</name>
          <children>
            <label row="0" col="0"><name>name</name>
              <args>
                <label type="encoded">'.urlencode( $gLocale->GetStr( 'account.label' ) ).'</label>
              </args>
            </label>
            <combobox row="0" col="1"><name>accountid</name>
              <args>
                <disp>action</disp>
                <elements type="array">'.huixml_encode( $accounts ).'</elements>
              </args>
            </combobox>
          </children>
        </grid>
      </children>
    </form>
    <horizbar><name>hb</name></horizbar>
    <button><name>apply</name>
      <args>
        <themeimage>button_ok</themeimage>
        <formsubmit>newrepository</formsubmit>
        <horiz>true</horiz>
        <frame>false</frame>
        <label type="encoded">'.urlencode( $gLocale->GetStr( 'new_repository.submit' ) ).'</label>
        <action type="encoded">'.urlencode( build_events_call_string( '', array(
            array( 'main', 'default', '' ),
            array( 'action', 'newrepository', '' )
            ) ) ).'</action>
      </args>
    </button>
  </children>
</vertgroup>';

    $gPage_title .= ' - '.$gLocale->GetStr( 'newrepository.title' );
}

function repmodules_list_action_builder( $pageNumber )
{
    $temp_disp = new HuiDispatcher( 'main' );
    $event_data = $temp_disp->GetEventData();

    return build_events_call_string( '', array( array( 'main', 'repositorymodules', array(
        'id' => $event_data['id'],
        'repid' => $event_data['repid'],
        'pagenumber' => $pageNumber,
        'tab' => isset( $event_data['tab'] ) ? $event_data['tab'] : ''
        ) ) ) );
}

function modules_tab_action_builder( $tab )
{
    $main_disp = new HuiDispatcher( 'main' );
    $event_data = $main_disp->GetEventData();

    return build_events_call_string( '',
        array(
            array(
                'main',
                'repositorymodules',
                array(
                    'id' => $event_data['id'],
                    'repid' => $event_data['repid'],
                    'tab' => $tab
                    )
                )
        ) );
}

$gMain_disp->AddEvent( 'repositorymodules', 'main_repositorymodules' );
function main_repositorymodules( $eventData )
{
    global $gEnv, $gLocale, $gXml_def, $gPage_title, $gToolbars;
    OpenLibrary( 'modules.library' );

    $ac_remote = new AmpCentralRemoteServer( $gEnv['root']['db'], $eventData['id'] );

    $avail_reps = $ac_remote->ListAvailableRepositories(
        isset( $eventData['refresh'] ) ? true : false );

    $avail_mods_list = $ac_remote->ListAvailableModules(
        $eventData['repid'],
        isset( $eventData['refresh'] ) ? true : false );

    $avail_mods_sorted_list = array();
    $tabs = array();

    foreach ( $avail_mods_list as $id => $data )
    {
        $avail_mods_sorted_list[$data['category'] ? $data['category'] : 'various'][$id] = $data;
    }

    ksort( $avail_mods_sorted_list );

    foreach ( $avail_mods_sorted_list as $category => $data )
    {
        $tabs[]['label'] = ucfirst( $category ? $category : 'various' );
    }
    reset( $avail_mods_sorted_list );

    $x_account = new XmlRpcAccount( $gEnv['root']['db'], $ac_remote->mAccountId );

    $headers[0]['label'] = $gLocale->GetStr( 'module.header' );
    $headers[1]['label'] = $gLocale->GetStr( 'lastversion.header' );
    $headers[2]['label'] = $gLocale->GetStr( 'dependencies.header' );
    $headers[3]['label'] = $gLocale->GetStr( 'installed_version.header' );

    $gXml_def =
'<vertgroup><name>modules</name>
  <children>
    <label><name>title</name>
      <args>
        <bold>true</bold>
        <label type="encoded">'.urlencode( $x_account->mName.' - '.$avail_reps[$eventData['repid']]['name'] ).'</label>
      </args>
    </label>
    <tab><name>'.$eventData['repid'].'repmodules</name>
      <args>
        <tabactionfunction>modules_tab_action_builder</tabactionfunction>
        <tabs type="array">'.huixml_encode( $tabs ).'</tabs>
        <activetab>'.( isset( $eventData['tab'] ) ? $eventData['tab'] : '' ).'</activetab>
      </args>
      <children>';

    foreach ( $avail_mods_sorted_list as $avail_mods )
    {
    $gXml_def .=
'    <table><name>modules</name>
      <args>
        <headers type="array">'.huixml_encode( $headers ).'</headers>
        <rowsperpage>10</rowsperpage>
        <pagesactionfunction>repmodules_list_action_builder</pagesactionfunction>
        <pagenumber>'.( isset( $eventData['pagenumber'] ) ? $eventData['pagenumber'] : '' ).'</pagenumber>
        <sessionobjectusername>'.
            $eventData['id'].'-'.
            $eventData['repid'].'-'.
            ( isset( $eventData['tab'] ) ? $eventData['tab'] : '' ).
        '</sessionobjectusername>
      </args>
      <children>';

    $row = 0;

    while ( list( $id, $data ) = each( $avail_mods ) )
    {
        $mod_query = &$gEnv['root']['db']->Execute( 'SELECT modversion '.
            'FROM modules '.
            'WHERE modid='.$gEnv['root']['db']->Format_Text( $data['modid'] ) );

        if ( strlen( $data['dependencies'] ) )
        {
            $mod_deps = new ModuleDep( $gEnv['root']['db'] );
            $dep_check = $mod_deps->CheckModuleDeps(
                0,
                '',
                $mod_deps->ExplodeDeps( $data['dependencies'] ) );
        }
        else
        {
            $dep_check = false;
        }

        if ( $mod_query->NumRows() ) $current_version = $mod_query->Fields( 'modversion' );
        else $current_version = $gLocale->GetStr( 'none_version.label' );

        if ( $dep_check == false )
        {
            $mod_installable = true;
            $missing_deps = '';

            if ( $mod_query->NumRows() )
            {
                switch ( CompareVersionNumbers(
                    $data['lastversion'],
                    $current_version ) )
                {
                case AMPOLIROS_VERSIONCOMPARE_EQUAL:
                    $label = $gLocale->GetStr( 'reinstall_module.button' );
                    $icon = 'reload';
                    break;

                case AMPOLIROS_VERSIONCOMPARE_MORE:
                    $label = $gLocale->GetStr( 'update_module.button' );
                    $icon = 'folder_new';
                    break;

                case AMPOLIROS_VERSIONCOMPARE_LESS:
                    $label = $gLocale->GetStr( 'downgrade_module.button' );
                    $icon = 'down';
                    break;
                }
            }
            else
            {
                $label = $gLocale->GetStr( 'install_module.button' );
                $icon = 'folder';
            }
        }
        else
        {
            $mod_installable = false;

            $missing_deps = '<br><strong>'.$gLocale->GetStr( 'missing_deps.label' ).'</strong>';

            while ( list( , $dep ) = each( $dep_check ) )
            {
                $missing_deps .= '<br>'.$dep;
            }
        }

        $toolbars = array();

        $toolbars['main']['versions'] = array(
            'label' => $gLocale->GetStr( 'module_versions.button' ),
            'themeimage' => 'view_detailed',
            'horiz' => 'true',
            'action' => build_events_call_string( '', array(
                array( 'main', 'moduleversions', array(
                    'id' => $eventData['id'],
                    'repid' => $eventData['repid'],
                    'moduleid' => $id ) ) ) ) );

        if ( $mod_installable )
        {
            $toolbars['main']['install'] = array(
                'label' => $label,
                'themeimage' => $icon,
                'horiz' => 'true',
                'action' => build_events_call_string( '', array(
                    array( 'main', 'repositorymodules', array(
                        'id' => $eventData['id'],
                        'repid' => $eventData['repid'] ) ),
                    array( 'action', 'installmodule', array(
                        'id' => $eventData['id'],
                        'repid' => $eventData['repid'],
                        'moduleid' => $id ) ) ) ) );
        }

        $gXml_def .=
'<label row="'.$row.'" col="0"><name>module</name>
  <args>
    <label type="encoded">'.urlencode( '<strong>'.$data['modid'].'</strong><br>'.$data['description'] ).'</label>
  </args>
</label>

<label row="'.$row.'" col="1"><name>lastversion</name>
  <args>
    <label type="encoded">'.urlencode( $data['lastversion'].'<br>('.$data['date'].')' ).'</label>
  </args>
</label>

<label row="'.$row.'" col="2"><name>dependencies</name>
  <args>
    <label type="encoded">'.urlencode( str_replace( ',', '<br>', $data['dependencies'] ).
    ( strlen( $data['suggestions'] ) ? '<br><br><strong>'.$gLocale->GetStr( 'suggestions.label' ).'</strong><br>'.str_replace( ',', '<br>', $data['suggestions'] ).'<br>' : '' ).$missing_deps ).'</label>
  </args>
</label>

<label row="'.$row.'" col="3"><name>current</name>
  <args>
    <label type="encoded">'.urlencode( $current_version ).'</label>
  </args>
</label>

<amptoolbar row="'.$row.'" col="4"><name>tb</name>
  <args>
    <frame>false</frame>
    <toolbars type="array">'.huixml_encode( $toolbars ).'</toolbars>
  </args>
</amptoolbar>';
        $row++;
    }

    $gXml_def .=
'      </children>
    </table>';

    }

    $gXml_def .=
'      </children>
    </tab>
  </children>
</vertgroup>';

                $gToolbars['reptools'] = array(
				    'refresh' => array(
        				'label' => $gLocale->GetStr( 'refresh.button' ),
				        'themeimage' => 'reload',
                        'horiz' => 'true',
				        'action' => build_events_call_string( '', array(
                    array( 'main', 'repositorymodules', array(
                        'id' => $eventData['id'],
                        'repid' => $eventData['repid'],
                        'refresh' => '1'
                        ) ) ) ) ) );

    $gPage_title .= ' - '.$gLocale->GetStr( 'repositorymodules.title' );
}

$gMain_disp->AddEvent( 'moduleversions', 'main_moduleversions' );
function main_moduleversions( $eventData )
{
    global $gEnv, $gLocale, $gXml_def, $gPage_title, $gToolbars;
    OpenLibrary( 'modules.library' );

    $ac_remote = new AmpCentralRemoteServer(
        $gEnv['root']['db'],
        $eventData['id'] );
    
    $avail_reps = $ac_remote->ListAvailableRepositories(
        isset( $eventData['refresh'] ) ? true : false );

    $avail_mods = $ac_remote->ListAvailableModules(
        $eventData['repid'],
        isset( $eventData['refresh'] ) ? true : false );

    $mod_versions = $ac_remote->ListAvailableModuleVersions(
        $eventData['repid'],
        $eventData['moduleid'],
        isset( $eventData['refresh'] ) ? true : false );


    $x_account = new XmlRpcAccount( $gEnv['root']['db'], $ac_remote->mAccountId );

    $headers[0]['label'] = $gLocale->GetStr( 'version.header' );
    $headers[1]['label'] = $gLocale->GetStr( 'dependencies.header' );
    $headers[2]['label'] = $gLocale->GetStr( 'installed_version.header' );

    $gXml_def =
'<vertgroup><name>modules</name>
  <children>
    <label><name>title</name>
      <args>
        <bold>true</bold>
        <label type="encoded">'.urlencode( $x_account->mName.' - '.$avail_reps[$eventData['repid']]['name'].' - '.$avail_mods[$eventData['moduleid']]['modid'] ).'</label>
      </args>
    </label>
    <table><name>modules</name>
      <args>
        <headers type="array">'.huixml_encode( $headers ).'</headers>
        <rowsperpage>10</rowsperpage>
        <pagesactionfunction>repmodules_list_action_builder</pagesactionfunction>
        <pagenumber>'.( isset( $eventData['pagenumber'] ) ? $eventData['pagenumber'] : '' ).'</pagenumber>
        <sessionobjectusername>'.$eventData['id'].'-'.$eventData['repid'].'-'.$eventData['moduleid'].'</sessionobjectusername>
      </args>
      <children>';

    $row = 0;

    $mod_query = &$gEnv['root']['db']->Execute( 'SELECT modversion '.
        'FROM modules '.
        'WHERE modid='.$gEnv['root']['db']->Format_Text( $avail_mods[$eventData['moduleid']]['modid'] ) );

    while ( list( $version, $data ) = each( $mod_versions ) )
    {
        if ( strlen( $data['dependencies'] ) )
        {
            $mod_deps = new ModuleDep( $gEnv['root']['db'] );
            $dep_check = $mod_deps->CheckModuleDeps(
                0,
                '',
                $mod_deps->ExplodeDeps( $data['dependencies'] ) );
        }
        else
        {
            $dep_check = false;
        }

        if ( $mod_query->NumRows() ) $current_version = $mod_query->Fields( 'modversion' );
        else $current_version = $gLocale->GetStr( 'none_version.label' );

        if ( $dep_check == false )
        {
            $mod_installable = true;
            $missing_deps = '';

            if ( $mod_query->NumRows() )
            {
                switch ( CompareVersionNumbers(
                    $version,
                    $current_version ) )
                {
                case AMPOLIROS_VERSIONCOMPARE_EQUAL:
                    $label = $gLocale->GetStr( 'reinstall_module.button' );
                    $icon = 'reload';
                    break;

                case AMPOLIROS_VERSIONCOMPARE_MORE:
                    $label = $gLocale->GetStr( 'update_module.button' );
                    $icon = 'folder_new';
                    break;

                case AMPOLIROS_VERSIONCOMPARE_LESS:
                    $label = $gLocale->GetStr( 'downgrade_module.button' );
                    $icon = 'down';
                    break;
                }
            }
            else
            {
                $label = $gLocale->GetStr( 'install_module.button' );
                $icon = 'folder';
            }
        }
        else
        {
            $mod_installable = false;

            $missing_deps = '<br><strong>'.$gLocale->GetStr( 'missing_deps.label' ).'</strong>';

            while ( list( , $dep ) = each( $dep_check ) )
            {
                $missing_deps .= '<br>'.$dep;
            }
        }

        $toolbars = array();

        if ( $mod_installable )
        {
            $toolbars['main']['install'] = array(
                'label' => $label,
                'themeimage' => $icon,
                'horiz' => 'true',
                'action' => build_events_call_string( '', array(
                    array( 'main', 'repositorymodules', array(
                        'id' => $eventData['id'],
                        'repid' => $eventData['repid'] ) ),
                    array( 'action', 'installmodule', array(
                        'id' => $eventData['id'],
                        'repid' => $eventData['repid'],
                        'moduleid' => $eventData['moduleid'],
                        'version' => $version ) ) ) ) );
        }

        $gXml_def .=
'<label row="'.$row.'" col="0"><name>version</name>
  <args>
    <label type="encoded">'.urlencode( $version ).'</label>
  </args>
</label>

<label row="'.$row.'" col="1"><name>dependencies</name>
  <args>
    <label type="encoded">'.urlencode( str_replace( ',', '<br>', $data['dependencies'] ).
    ( strlen( $data['suggestions'] ) ? '<br><br><strong>'.$gLocale->GetStr( 'suggestions.label' ).'</strong><br>'.str_replace( ',', '<br>', $data['suggestions'] ).'<br>' : '' ).$missing_deps ).'</label>
  </args>
</label>

<label row="'.$row.'" col="2"><name>current</name>
  <args>
    <label type="encoded">'.urlencode( $current_version ).'</label>
  </args>
</label>

<amptoolbar row="'.$row.'" col="3"><name>tb</name>
  <args>
    <frame>false</frame>
    <toolbars type="array">'.huixml_encode( $toolbars ).'</toolbars>
  </args>
</amptoolbar>';
        $row++;
    }

    $gXml_def .=
'      </children>
    </table>
  </children>
</vertgroup>';

                $gToolbars['reptools'] = array(
				    'refresh' => array(
        				'label' => $gLocale->GetStr( 'refresh.button' ),
				        'themeimage' => 'reload',
                        'horiz' => 'true',
				        'action' => build_events_call_string( '', array(
                    array( 'main', 'moduleversions', array(
                        'id' => $eventData['id'],
                        'repid' => $eventData['repid'],
                        'moduleid' => $eventData['moduleid'],
                        'refresh' => '1'
                        ) ) ) ) ) );

    $gPage_title .= ' - '.$gLocale->GetStr( 'moduleversions.title' );
}

$gMain_disp->Dispatch();

// Rendering
//
if ( strlen( $gXml_def ) ) $gPage_content = new HuiXml( 'page', array( 'definition' => $gXml_def ) );

$gHui->AddChild( new HuiAmpPage( 'page', array(
    'pagetitle' => $gPage_title,
    'menu' => $gMenu,
    'toolbars' => array( new HuiAmpToolbar( 'main', array(
        'toolbars' => $gToolbars
        ) ) ),
    'maincontent' => $gPage_content,
    'status' => $gStatus
    ) ) );

$gHui->Render();

?>
