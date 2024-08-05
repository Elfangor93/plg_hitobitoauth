let getOAuthToken = function(event, baseUrl, client) {
  event.preventDefault();
  let winprops = 'height=500,width=400,top=100,left=100,scrollbars=1,resizable=yes,scrollbars=yes,toolbar=no,menubar=no,location=no,directories=no,status=no';
  let url = baseUrl+'?task=oauth&app=hitobito&from='+client;
  window.open(url, 'Hitobito OAuth2', winprops);
};

let getGroups = function(event) {
  event.preventDefault();

  let id = document.getElementById('jform_params_hitobito_groupid').value;
  let token = document.getElementById('jform_params_hitobito_grouptoken').value;
  let host = document.getElementById('jform_params_clienthost').value;

  if(token == '') {
    alert(Joomla.Text._('PLG_SYSTEM_HITOBITOAUTH_API_TOKEN_NEEDED'));
    return false;
  }

  let parameters = {
    method: 'GET', // *GET, POST, PUT, DELETE, etc.
    mode: 'cors', // no-cors, *cors, same-origin
    cache: 'default', // *default, no-cache, reload, force-cache, only-if-cached
    headers: {'Content-Type': 'application/json; charset=utf-8'}, // 'Content-Type': 'application/x-www-form-urlencoded',
    redirect: 'manual', // manual, *follow, error
    referrerPolicy: 'no-referrer-when-downgrade', // no-referrer, *no-referrer-when-downgrade, origin, ...
  };

  let url_ident = host+"/de/groups/"+id+".json?token="+token;

  async function getData(url, parameters) {
    let response = await fetch(url, parameters);

    if (!response.ok) {
      if (response.status == 0) {
        // on network error
        console.log('Network-Error: ' + response.status + ', ' + response.statusText);
      }
      if (response.status != 200) {
        // server error
        console.log('Server-Error: ' + response.status + ', ' + response.statusText);
      }
    } else {
      // on success
      return await response.json();
    }
  };

  getData(url_ident, parameters)
  .then(res => {
    if(res.hasOwnProperty('groups')) {
      let roles = res.groups[0].available_roles;
      openModal(roles);
    } else {
      openModal(false);
    }            
  })
  .catch(err => {
    let winprops = 'height=500,width=400,top=100,left=100,scrollbars=1,resizable=yes,scrollbars=yes,toolbar=no,menubar=no,location=no,directories=no,status=no';
    let win = window.open('','Error',winprops);
    win.document.body.innerHTML = '<h1>Error</h1><p>' + err + '</p><p>' + Joomla.Text._('PLG_SYSTEM_HITOBITOAUTH_API_ERROR_CORS') + '</p>';
  });
};

let openModal = function(content) {
    let winprops = 'height=500,width=400,top=100,left=100,scrollbars=1,resizable=yes,scrollbars=yes,toolbar=no,menubar=no,location=no,directories=no,status=no';
    let win = window.open('', Joomla.Text._('PLG_SYSTEM_HITOBITOAUTH_AVAILABLE_ROLES'), winprops);
    
    if (content == false) {
      win.document.body.innerHTML = '<h1>' + Joomla.Text._('PLG_SYSTEM_HITOBITOAUTH_AVAILABLE_ROLES') + '</h1><p>' + Joomla.Text._('PLG_SYSTEM_HITOBITOAUTH_NO_AVAILABLE_ROLES') + '</p>';
    } else {
      win.document.body.innerHTML = '<h1>' + Joomla.Text._('PLG_SYSTEM_HITOBITOAUTH_AVAILABLE_ROLES') + '</h1>';
      ul = win.document.createElement('ul');
      win.document.body.appendChild(ul);

      content.forEach(item => {
        let li = win.document.createElement('li');
        ul.appendChild(li);
        //li.innerHTML += item.name;
        li.innerHTML += item.role_class;
      });
    }        
};

let popupImage = function(event, url) {
  event.preventDefault();
  let winprops = 'height=400,width=600,top=100,left=100,scrollbars=1,resizable=yes,scrollbars=yes,toolbar=no,menubar=no,location=no,directories=no,status=no';
  window.open(url, Joomla.Text._('PLG_SYSTEM_HITOBITOAUTH_EXAMPLE_IMAGE'), winprops);
};
