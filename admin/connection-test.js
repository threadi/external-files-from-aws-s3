/**
 * Run the complete connection test for an S3-compatible storage and show its results.
 *
 * @param {string} type    The name of the platform.
 * @param {object} fields  The fields of the connection.
 * @param {number} term_id The ID of the used external source.
 */
function efmlawss3_connection_test( type, fields, term_id ) {
  // show a dialog while the test is running.
  efml_create_dialog( {
    detail: {
      className: 'efml',
      title: efmlawss3ConnectionTestVars.title,
      texts: [ '<p>' + efmlawss3ConnectionTestVars.text + '</p>' ],
      buttons: []
    }
  } );

  // run the test.
  jQuery.ajax( {
    url: efmlawss3ConnectionTestVars.ajax_url,
    type: 'POST',
    data: {
      action: 'efmlawss3_connection_test',
      type: type,
      fields: fields,
      term_id: term_id,
      nonce: efmlawss3ConnectionTestVars.nonce,
    },
    error: function( jqXHR, textStatus, errorThrown ) {
      efml_ajax_error_dialog( errorThrown );
    },
    success: function( dialog_config ) {
      efml_create_dialog( dialog_config );
    }
  } );
}
