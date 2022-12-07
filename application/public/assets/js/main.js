window.onload = function(){
    let cookie_policy=check_cookie_policy();

    var dataEventSource = new EventSource("data/?cookies_allowed="+cookie_policy);

    dataEventSource.addEventListener("liveqa_error", (event) => {receive_error_event(event);});
    dataEventSource.addEventListener("sys", (event) => {receive_sys_event(event);});
    dataEventSource.addEventListener("content", (event) => {receive_content_event(event);});
}

function receive_error_event(event){
    alert(event.data);
}

function receive_sys_event(event){
    var data = JSON.parse(event.data).data;
    
    data.forEach(function(chunk){
	if(chunk["type"] == "text"){
	    if(exists(chunk["host"])){
		document.getElementById("data_text_host").innerText = chunk["host"];
	    }
	    if(exists(chunk["headline"])){
		document.getElementById("data_text_headline").innerText = chunk["headline"];
	    }
	}

	if(chunk["type"] == "css"){
	    document.documentElement.style.setProperty(chunk["key"], chunk["value"]);
	}

	if(chunk["type"] == "project"){
	    if(exists(chunk["id"]) && exists(chunk["name"])){
		if(!document.getElementById("einstellungen_set_active_project_option_"+chunk["id"])){
		    var selectOption = document.createElement("option");
		    document.getElementById("einstellungen_set_active_project").appendChild(selectOption);
		    selectOption.setAttribute("id", "einstellungen_set_active_project_option_"+chunk["id"]);
		    selectOption.setAttribute("value", chunk["id"]);
		}else{
		    var selectOption = document.getElementById("einstellungen_set_active_project_option_"+chunk["id"]);
		}
		
		selectOption.innerText=chunk["id"]+": "+chunk["name"];
	    }

	    if(exists(chunk["id"]) && exists(chunk["active"])){
		let selectOption = document.getElementById("einstellungen_set_active_project_option_"+chunk["id"]); 
		if(chunk["active"] == 1){
		    selectOption.selected=true;
		    selectOption.innerText+=" (aktiv)";
		}
		if(chunk["active"] == 0){
		    selectOption.selected=false;
		    selectOption.innerText=removeLastWordIf(" (aktiv)", selectOption.innerText);
		}
	    }
	}

	if(chunk["type"] == "user"){
	    if(exists(chunk["unset"]) && chunk["unset"] == 1){
		disable_user_interactions();
	    }
	    
	    if(exists(chunk["name"])){
		document.getElementById("data_username").innerText=chunk["name"];
		document.getElementById("nutzereinstellungen_set_user_name").value=chunk["name"];
		document.getElementById("frage_stellen_set_user_name").value=chunk["name"];
	    }
	    if(exists(chunk["session"])){
		document.getElementById("data_session").value=chunk["session"];
	    }
	    if(exists(chunk["level"]) && parseInt(chunk["level"]) >= 0 && parseInt(chunk["level"]) <= 3){
		chunk["level"]=parseInt(chunk["level"]);
		
		document.getElementById("frage_stellen_set_user_level_option_0").selected=false;
		document.getElementById("frage_stellen_set_user_level_option_1").selected=false;
		document.getElementById("frage_stellen_set_user_level_option_2").selected=false;
		document.getElementById("frage_stellen_set_user_level_option_3").selected=false;

		document.getElementById("nutzerinformationen_set_user_level_option_0").selected=false;
		document.getElementById("nutzerinformationen_set_user_level_option_1").selected=false;
		document.getElementById("nutzerinformationen_set_user_level_option_2").selected=false;
		document.getElementById("nutzerinformationen_set_user_level_option_3").selected=false;
		
		if(chunk["level"] == 0){
		    document.getElementById("frage_stellen_set_user_level_option_0").selected=true;
		    document.getElementById("nutzerinformationen_set_user_level_option_0").selected=true;
		    document.getElementById("data_level").innerText="Anfänger";
		}
		if(chunk["level"] == 1){
		    document.getElementById("frage_stellen_set_user_level_option_1").selected=true;
		    document.getElementById("nutzerinformationen_set_user_level_option_1").selected=true;
		    document.getElementById("data_level").innerText="Normaler Nutzer";
		}
		if(chunk["level"] == 2){
		    document.getElementById("frage_stellen_set_user_level_option_2").selected=true;
		    document.getElementById("nutzerinformationen_set_user_level_option_2").selected=true;
		    document.getElementById("data_level").innerText="Fortgeschrittener";
		}
		if(chunk["level"] == 3){
		    document.getElementById("frage_stellen_set_user_level_option_3").selected=true;
		    document.getElementById("nutzerinformationen_set_user_level_option_3").selected=true;
		    document.getElementById("data_level").innerText="Profi";
		}
	    }
	    if(exists(chunk["os"])){
		document.getElementById("data_os").innerText=chunk["os"];
		document.getElementById("frage_stellen_set_user_os").value=chunk["os"];
		document.getElementById("nutzerinformationen_set_user_os").value=chunk["os"];
	    }
	    if(exists(chunk["mod"])){
		document.getElementById("data_mod_0").selected=false;
		document.getElementById("data_mod_1").selected=false;

		if(chunk["mod"] == 0){
		    document.getElementById("data_mod").innerText="User";
		    document.getElementById("data_mod_0").selected=true;
		    
		    document.querySelectorAll('._mod').forEach(e => e.remove());
		}
		if(chunk["mod"] == 1){
		    document.getElementById("data_mod").innerText="Moderator";
		    document.getElementById("data_mod_1").selected=true;

		    document.querySelectorAll('._not_mod').forEach(e => e.remove());
		}		
	    }
	}
    });
}

function receive_content_event(event){
    var data = JSON.parse(event.data).data;
    
    data.forEach(function(chunk){
	if(chunk["type"] == "frage"){
	    if(exists(chunk["id"]) && Number.isInteger(chunk["id"]) && !document.getElementById("frage_"+chunk["id"])){
		let frage_html = `
<input type="checkbox" class="pseudo" id="togglefrage_${chunk["id"]}">
<label class="frage" id="frage_${chunk["id"]}" for="togglefrage_${chunk["id"]}">
</label>
`;
		document.getElementById("fragen").innerHTML+=frage_html;
	    }
	}
    });
}

function exists(i){
    if(i === undefined){
	return false;
    }else{
	return true;
    }
}

function disable_user_interactions(){
    document.querySelectorAll('._interaction').forEach(e => e.style.display="none");
}

function check_cookie_policy(){
    if(!navigator.cookieEnabled){
	disable_user_interactions();
	return "0";
    }
    return "1";
}

function api_request(component){
    if(component.includes("einstellungen_new_project")){
	let project_name = document.getElementById("einstellungen_new_project").value;
	if(project_name != ""){
	    fetch( '/api/?group=sys&action=new&type=project&content='+encodeURIComponent(project_name) );
	    document.getElementById("einstellungen_new_project").value="";
	}
    }

    if(component.includes("einstellungen_set_active_project")){
	let project_id = document.getElementById("einstellungen_set_active_project").value;
	if(project_id != ""){
	    fetch( '/api/?group=sys&action=set&type=project&property=active&id='+encodeURIComponent(project_id) );
	}
    }

    if(component.includes("nutzereinstellungen_set_user_session")){
	let session_id = document.getElementById("nutzereinstellungen_set_user_session").value;
	if(session_id != ""){
	    fetch( '/api/?group=sys&action=set&type=user&property=session&content='+encodeURIComponent(session_id) )
		.then(function(){location.reload();});
	}
    }

    if(component.includes("nutzereinstellungen_set_user_name") || component.includes("frage_stellen_set_user_name")){
	if(component.includes("frage_stellen_set_user_name")){
	    var username = document.getElementById("frage_stellen_set_user_name").value;
	}else{
	    var username = document.getElementById("nutzereinstellungen_set_user_name").value;
	}
	if(username != ""){
	    fetch( '/api/?group=sys&action=set&type=user&property=name&content='+encodeURIComponent(username) );
	}
    }

    if(component.includes("nutzerinformationen_set_user_level") || component.includes("frage_stellen_set_user_level")){
	if(component.includes("frage_stellen_set_user_level")){
	    var level = document.getElementById("frage_stellen_set_user_level").value;
	}else{
	    var level = document.getElementById("nutzerinformationen_set_user_level").value;
	}
	if(parseInt(level) >= 0 && parseInt(level) <= 3){
	    fetch( '/api/?group=sys&action=set&type=user&property=level&content='+encodeURIComponent(level) );
	}
    }

    if(component.includes("nutzerinformationen_set_user_os") || component.includes("frage_stellen_set_user_os")){
	if(component.includes("frage_stellen_set_user_os")){
	    var os = document.getElementById("frage_stellen_set_user_os").value;
	}else{
	    var os = document.getElementById("nutzerinformationen_set_user_os").value;
	}
	fetch( '/api/?group=sys&action=set&type=user&property=os&content='+encodeURIComponent(os) );
    }

    if(component.includes("frage_stellen_new_frage")){
	var frage = document.getElementById("frage_stellen_new_frage").value;
	fetch( '/api/?group=sys&action=new&type=frage&content='+encodeURIComponent(frage) );
    }
}

function removeLastWordIf(check, string) {
    if(string.endsWith(check)){
	return string.slice(0, check.length*-1);
    }else{
	return string;
    }
}
