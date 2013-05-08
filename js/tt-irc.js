var window_active = true;
var last_id = 0;
var last_old_id = 0;
var new_messages = 0;
var new_highlights = 0;
var delay = 1500;
var timeout_delay = 3000;
var topics = [];
var last_update = false;
var input_cache = [];
var input_cache_offset = 0;
var highlight_on = [];
var notify_events = [];
var theme_images = [];
var update_delay_max = 0;
var theme = "";
var hide_join_part = false;
var startup_date;
var id_history = [];
var uniqid;
var emoticons_map = false;
var autocomplete = [];
var autocompleter = false;
var topic_autocompleter = false;

var timeout_id = false;
var update_id = false;

var MSGT_PRIVMSG = 0;
var MSGT_COMMAND = 1;
var MSGT_BROADCAST = 2;
var MSGT_ACTION = 3;
var MSGT_TOPIC = 4;
var MSGT_PRIVATE_PRIVMSG = 5;
var MSGT_EVENT = 6;
var MSGT_NOTICE = 7;
var MSGT_SYSTEM = 8;

var CS_DISCONNECTED = 0;
var CS_CONNECTING = 1;
var CS_CONNECTED = 2;

var CT_CHANNEL = 0;
var CT_PRIVATE = 1;

var initial = true;

var Connection = function(data) {
	var self = this;

	self.status = ko.observable(0);
	self.getConnImg = function() {
		return self.status() == 2 ? "images/srv_online.png" : "images/srv_offline.png";
	};

	self.id = ko.observable(0);
	self.connection_id = ko.observable(0);
	self.active_nick = ko.observable("");
	self.active_server = ko.observable("");
	self.title = ko.observable("");
	self.userhosts = ko.observableArray([]);
	self.channels = ko.observableArray([]);
	self.lines = ko.observableArray([]);
	self.status = ko.observable(0);
	self.nicklist = ko.observableArray([]);
	self.highlight = ko.observable(false);
	self.attention = ko.observable(false);
	self.type = ko.observable("S");

	self.update = function(data) {
		self.id(data.id);
		self.connection_id(data.id);
		self.active_nick(data.active_nick);
		self.active_server(data.active_server);
		self.title(data.title);
		self.userhosts(data.userhosts);
		self.status(parseInt(data.status));
	};

	self.connected = ko.computed(function() {
		return self.status() == CS_CONNECTED;
	}, self);

	self.selected = ko.computed(function() {
		return model.activeChannel() == self;
	});

	self.nickExists = function(nick) {
		for (var i = 0; i < self.channels().length; i++) {
			if (self.channels()[i].nicklist() && this.channels()[i].nicklist().nickIndexOf(nick) !== -1)
				return true;
		}
	};

	self.topic = ko.computed({
		read: function() {
			var topic = "";

			switch (self.status()) {
			case CS_CONNECTING:
				topic = __("Connecting...");
				break;
			case CS_CONNECTED:
				var topic = __("Connected to: ") + self.active_server();
				break;
			case CS_DISCONNECTED:
				topic = __("Disconnected.");
				break;
			}

			return [topic];
		},
		write: function(topic) {
			//
		},
		owner: self
	});

	self.topicDisabled = ko.computed(function() {
		return true;
	}, self);

	self.update(data);
};

var Message = function(data) {
	var self = this;

	self.id = ko.observable(data.id);
	self.message_type = ko.observable(parseInt(data.message_type));
	self.sender = ko.observable(data.sender);
	self.channel = ko.observable(data.channel);
	self.connection_id = ko.observable(data.connection_id);
	self.incoming = ko.observable(data.incoming);
	self.message = ko.observable(data.message);
	self.ts = ko.observable(data.ts);
	self.sender_color = ko.observable(data.sender_color);
	self.is_hl = ko.observable(is_highlight(data.connection_id, data));

	if (emoticons_map && self.message()) {
		self.message(rewrite_emoticons(self.message()));
	}

	self.format = ko.computed(function() {
		var nick_ext_info = model.getNickHost(self.connection_id(), self.sender());

		switch (self.message_type()) {
		case MSGT_ACTION:
			return "<span class='timestamp'>" +
				make_timestamp(self.ts()) + "</span> " +
				"<span class='action'> * " + self.sender() + " " + self.message() + "</span>";
			break;
		case MSGT_NOTICE:

			var sender_class = self.incoming() == true ? 'pvt-sender' : 'pvt-sender-out';

			return "<span class='timestamp'>" +
				make_timestamp(self.ts()) +
				"</span> <span class='lt'>-</span><span title=\""+nick_ext_info+"\" " +
				"class='"+sender_class+"' style=\"color : "+colormap[self.sender_color()]+"\">" +
				self.sender() + "</span><span class='gt'>-</span> " +
				"<span class='message'>" +
				self.message() + "</span>";

			break;
		case MSGT_SYSTEM:

			return "<span class='timestamp'>" +
				make_timestamp(self.ts()) + "</span> " +
				"<span class='sys-message'>" +
				self.message() + "</span>";

			break;
		default:
			if (self.sender() != "---") {
				return "<span class='timestamp'>" +
					make_timestamp(self.ts()) +
					"</span> <span class='lt'>&lt;</span><span title=\""+nick_ext_info+"\" " +
					"class='sender' style=\"color : "+colormap[self.sender_color()]+"\">" +
					self.sender() + "</span><span class='gt'>&gt;</span> " +
					"<span class='message'>" +
					self.message() + "</span>";
			} else {
				return "<span class='timestamp'>" +
					make_timestamp(self.ts()) + "</span> " +
					"<span class='sys-message'>" +
					self.message() + "</span>";
			}
		}

	});
}

var Channel = function(connection_id, title, tab_type) {
	var self = this;

	self.title = ko.observable(title);
	self.type = ko.observable(tab_type);
	self.nicklist = ko.observableArray([]);
	self.connection_id = ko.observable(connection_id);
	self.lines = ko.observableArray([]);
	self._topic = ko.observableArray([]);
	self.topicEventSynthesized = ko.observable(false);
	self.highlight = ko.observable(false);
	self.attention = ko.observable(false);

	self.topicDisabled = ko.computed(function() {
		return self.type() != "C";
	}, self);

	self.topic = ko.computed({
		read: function() {
			switch (self.type()) {
			case "P":
				var nick_ext_info = model.getNickHost(self.connection_id(), self.title());
				var topic = __("Conversation with") + " " +
					self.title();

				if (nick_ext_info)
					topic = topic + " (" + nick_ext_info + ")";

				return [topic];
			default:
				return self._topic();
			}
		},
		write: function(topic) {
			if (topic[0] != "" && !self.topicEventSynthesized()) {
				self.topicEventSynthesized(true);

				var line = new Object();

				line.message = __("Topic for %c is: %s").replace("%c", self.title()).
					replace("%s", topic[0])

				line.message_type = MSGT_SYSTEM;
				line.ts = new Date();
				line.id = last_id;
				line.force_display = 1;

				push_message(self.connection_id(), self.title(), line, MSGT_PRIVMSG);

				line.message = __("Topic for %c set by %n at %d").replace("%c", self.title()).
					replace("%n", topic[1]).
					replace("%d", rewrite_urls(topic[2]));

				line.message_type = MSGT_SYSTEM;
				line.ts = new Date();
				line.id = last_id;
				line.force_display = 1;

				push_message(self.connection_id(), self.title(), line, MSGT_PRIVMSG);
			}

			self._topic(topic);
		},
		owner: self
	});

	self.selected = ko.computed(function() {
		return model.activeChannel() == self;
	});

	self.offline = ko.computed(function() {
		if (self.type() == "P") {
			var conn = model.getConnection(self.connection_id());
			if (conn)
				return !conn.nickExists(self.title());
		} else {
			return false;
		}
	}, self);
};

function Model() {
	var self = this;

	self.connections = ko.observableArray([]);
	self._activeChannel = ko.observable("");
	self._activeConnectionId = ko.observable(0);

	self.getNickHost = function(connection_id, nick) {
		var nick = self.stripNickPrefix(nick);
		var conn = self.getConnection(connection_id);

		if (conn && conn.userhosts()[nick]) {
			return conn.userhosts()[nick][0] + '@' +
				conn.userhosts()[nick][1] + " <" + conn.userhosts()[nick][3] + ">";
		}
	};

	self.stripNickPrefix = function(nick) {
		if (nick)
			return nick.replace(/^[\@\+]/, "");
		else
			return "";
	};

	self.getNickImage = function(nick) {
		switch (nick.substr(0,1)) {
		case "@":
			return theme_images['user_op.png'];
		case "+":
			return theme_images['user_voice.png'];
		default:
			return theme_images['user_normal.png'];
		}
	};

	self.cleanupChannels = function(connection_id, titles) {
		var conn = self.getConnection(connection_id);

		if (conn) {
			var i = 0;
			while (i < conn.channels().length) {
				if (titles.indexOf(conn.channels()[i].title()) == -1)
					conn.channels.remove(conn.channels()[i]);

				i++;
			}
		}
	};

	self.cleanupConnections = function(ids) {
		var i = 0;
		while (i < self.connections().length) {
			if (ids.indexOf(self.connections()[i].id()) == -1)
				self.connections.remove(self.connections()[i]);

			i++;
		}
	};

	self.getConnection = function(id) {
		for (var i = 0; i < self.connections().length; i++) {
			if (self.connections()[i].id() == id)
				return self.connections()[i];
		}
	};

	self.getChannel = function(id, title) {
		var conn = self.getConnection(id);
		if (conn) {
			if (title == "---")
				return conn;

			for (var i = 0; i < conn.channels().length; i++) {
				if (conn.channels()[i].title() == title)
					return conn.channels()[i];
			}
		}
	};

	self.activeConnection = ko.computed(function() {
		return self.getConnection(self._activeConnectionId());
	}, self);

	self.activeNick = ko.computed(function() {
		var conn = self.activeConnection();

		if (conn && conn.active_nick)
			return conn.active_nick();

	}, self);

	self.isAway = ko.computed(function() {
		var conn = self.activeConnection();

		if (conn && conn.active_nick)
			if (conn.userhosts && conn.userhosts()[conn.active_nick()])
				return conn.userhosts()[conn.active_nick()][4] == true;

	}, self);

	self.activeChannel = ko.computed({
		read: function() {
			return self.getChannel(self._activeConnectionId(), self._activeChannel());
		},
		write: function(connection_id, channel) {
			self._activeConnectionId(connection_id);
			self._activeChannel(channel);

			var chan = self.getChannel(connection_id, channel);

			if (chan) {
				chan.highlight(false);
				chan.attention(false);
			}
		},
		owner: self});

	self.connectBtnLabel = ko.computed(function() {
		var conn = self.activeConnection();

		if (conn && conn.status) {
			switch (conn.status()) {
			case CS_CONNECTING:
				return __("Connecting...");
			case CS_CONNECTED:
				return __("Disconnect");
			case CS_DISCONNECTED:
				return __("Connect");
			}

		} else {
			return __("Connect");
		}

	}, self);

	self.toggleConnection = ko.computed({
		read: function() {
			return self.activeConnection;
		},
		write: function() {
			var conn = self.activeConnection();

			if (conn)
				toggle_connection(conn.id(), conn.connected() ? 0 : 1);
		},
		owner: self
	});

	self.activeTopicFormatted = ko.computed(function() {
		var chan = self.activeChannel();

		if (chan)
			return rewrite_emoticons(chan.topic()[0]);

	}, self);

	self.activeTopic = ko.computed(function() {
		var chan = self.activeChannel();

		if (chan)
			return chan.topic()[0];

	}, self);

	self.activeTopicDisabled = ko.computed(function() {
		var chan = self.activeChannel();

		if (chan)
			return chan.topicDisabled();
		else
			return true;

	}, self);

	self.activeStatus = ko.computed(function() {
		return self.activeConnection() && self.activeConnection().status() || CS_DISCONNECTED;
	}, self);
};

var model = new Model();

var colormap = [ "#00CCCC", "#000000", "#0000CC", "#CC00CC", "#606060",
	"green", "#00CC00", "maroon", "navy", "olive", "purple",
	"red", "#909090", "teal", "#CCCC00" ]

var commands = [ "/join", "/part", "/nick", "/query", "/quote", "/msg",
	"/op", "/deop", "/voice", "/devoice", "/ping", "/notice", "/away",
	"/ctcp", "/clear" ];


function toggle_sidebar() {
try {
		if (Element.visible('sidebar-inner')) {
			Element.hide("sidebar-inner");
			$("log").setStyle({
				right : "5px",
			});

			$("sidebar").setStyle({
				width : "5px",
			});

			$("topic").setStyle({
				right : "5px",
			});

			$("input").setStyle({
				right : "5px",
			});

		} else {
			Element.show("sidebar-inner");

			$("log").setStyle({
				right : "155px",
			});

			$("sidebar").setStyle({
				width : "155px",
			});

			$("topic").setStyle({
				right : "155px",
			});

			$("input").setStyle({
				right : "155px",
			});

		}
	} catch (e) {
		exception_error("toggle_sidebar", e);
	}
}

function init_second_stage(transport) {
	try {

		var params = JSON.parse(transport.responseText);

		if (!handle_error(params, transport)) return false;

		if (!params || params.status != 1) {
			return fatal_error(14, __("The application failed to initialize."),
				transport.responseText);
		}

		last_old_id = params.max_id;
		theme_images = params.images;
		update_delay_max = params.update_delay_max;
		theme = params.theme;
		uniqid = params.uniqid;
		emoticons_map = params.emoticons;

		Element.hide("overlay");

		$("input-prompt").value = "";
		$("input-prompt").focus();

		if (navigator.appName.indexOf("Microsoft Internet") == -1) {
			autocompleter = new Autocompleter.Local("input-prompt",
				"input-suggest", autocomplete, {tokens: ' ',
					choices : 5,
					afterUpdateElement: function(element) { element.value += " " ; },
					onShow: function(element, update) { Element.show(update); return true; } });

			topic_autocompleter = new Autocompleter.Local("topic-input-real",
				"topic-suggest", autocomplete, {tokens: ' ',
					choices : 5,
					afterUpdateElement: function(element) { element.value += " " ; },
					onShow: function(element, update) { Element.show(update); return true; } });

			autocomplete = [];

			for (key in emoticons_map) {
				autocomplete.push(key);
			}

			for (var i = 0; i < commands.length; i++) {
				autocomplete.push(commands[i]);
			}

			autocompleter.options.array = autocomplete;
			topic_autocompleter.options.array = autocomplete;
		}

		console.log("init_second_stage");

		document.onkeydown = hotkey_handler;

		enable_hotkeys();

		hide_spinner();

		update(true);

		window.setTimeout("title_timeout()", 1000);

	} catch (e) {
		exception_error("init_done", e);
	}
}

function init() {
	try {
		if (getURLParam('debug')) {
			Element.show("debug_output");
			console.log('debug mode activated');
		}

		show_spinner();

		ko.applyBindings(model);

		new Ajax.Request("backend.php", {
		parameters: "op=init",
		onComplete: function (transport) {
			init_second_stage(transport);
		} });

	} catch (e) {
		exception_error("init", e);
	}
}

function handle_update(transport) {
	try {
		var rv = false;

		try {
			rv = JSON.parse(transport.responseText);
		} catch (e) {
			console.log(e);
		}

		if (!rv) {
			console.log("received null object from server, will try again.");
			Element.show("net-alert");
			return true;
		} else {
			Element.hide("net-alert");
		}

		if (!handle_error(rv, transport)) return false;

		var conn_data = rv[0];
		var lines = rv[1];
		var chandata = rv[2];
		var params = rv[3];

		var ts = new Date().getTime();

		$$(".applied").each(function(e) {
			if (parseInt(e.getAttribute("applied_at")) < ts - 6000) {
				e.removeClassName("applied");
			}
		});

		if (params && !params.duplicate) {
			highlight_on = params.highlight_on;

			/* we can't rely on PHP mb_strtoupper() since it sucks cocks */

			for (var i = 0; i < highlight_on.length; i++) {
				highlight_on[i] = highlight_on[i].toUpperCase();
			}

			notify_events = params.notify_events;
			hide_join_part = params.hide_join_part;
			uniqid = params.uniqid;
		}

		last_update = new Date();

		handle_conn_data(conn_data);
		handle_chan_data(chandata);

		if (initial) {
			var c = hash_get();

			if (c) {
				c = c.split(",");

				if (c.size() == 2) {
					var tab = find_tab(c[0], c[1]);

					if (tab) change_tab(tab);
				}
			}

			startup_date = new Date();

			initial = false;
		}

		var prev_last_id = last_id;

		for (var i = 0; i < lines.length; i++) {

			if (last_id < lines[i].id) {

//				console.log("processing line ID " + lines[i].id);

				var chan = lines[i].channel;
				var connection_id = lines[i].connection_id;

				//lines[i].message += " [" + lines[i].id + "/" + last_id + "]";

				lines[i].ts = lines[i].ts.replace(/\-/g, '/');
				lines[i].ts = new Date(Date.parse(lines[i].ts));

				if (lines[i].message_type == MSGT_EVENT) {
					handle_event(connection_id, lines[i]);
				} else {
					push_message(connection_id, chan, lines[i], lines[i].message_type);
					if (!window_active) ++new_messages;
				}

			}

			last_id = lines[i].id;
		}

		if (!get_selected_tab()) {
			change_tab(get_all_tabs()[0]);
		}

		if (prev_last_id != last_id)
			update_buffer();

		if (prev_last_id == last_id && update_delay_max == 0) {
			if (delay < 3000) delay += 500;
		} else {
			delay = 1500;
		}

	} catch (e) {
		exception_error("handle_update", e);
	}

	apply_anim_classes();

	return true;
}

function apply_anim_classes() {
	try {
		if (Math.random() > 0.10) return;

		var elems = Math.random() > 0.5 ? $$("span.anim") : $$("img.anim");

		if (elems.size() > 0) {

			if (elems.size() > 3)
				elems = elems.slice(elems.size()-3, elems.size());

			var index = parseInt(Math.random()*elems.size());

			var e = elems[index];
			var ts = new Date().getTime();

			if (e && !e.hasClassName("applied")) {
				e.addClassName("applied");
				e.setAttribute("applied_at", ts);

				window.setTimeout(function() {
					e.removeClassName("applied")
				}, 6000);

			}
		}

	} catch (e) {
		exception_error("apply_anim_classes", e);
	}
}

function timeout() {
	try {
		console.log("update timeout detected, retrying...");

		window.clearTimeout(update_id);
		update_id = window.setTimeout("update()", timeout_delay);

	} catch (e) {
		exception_error("timeout", e);
	}
}

function update(init) {
	try {
		var query = "op=update&last_id=" + last_id + "&uniqid=" + uniqid;

		if (init) query += "&init=" + init;

//		console.log("request update..." + query + " last: " + last_update);

		timeout_id = window.setTimeout("timeout()",
			(update_delay_max * 1000) + 10000);

		new Ajax.Request("backend.php", {
		parameters: query,
		onComplete: function (transport) {
			window.clearTimeout(timeout_id);
			window.clearTimeout(update_id);
			if (!handle_update(transport)) return;

//			console.log("update done, next update in " + delay + " ms");

			update_id = window.setTimeout("update()", delay);
		} });

	} catch (e) {
		exception_error("update", e);
	}
}

function get_selected_tab() {
	try {
		var tabs = $("tabs-list").getElementsByTagName("li");

		for (var i = 0; i < tabs.length; i++) {
			if (tabs[i].hasClassName("selected")) {
				return tabs[i];
			}
		}

		return false;

	} catch (e) {
		exception_error("get_selected_tab", e);
	}
}

function get_all_tabs(connection_id) {
	try {
		var tabs;
		var rv = [];

		if (connection_id) {
			tabs = $("tabs-" + connection_id).getElementsByTagName("LI");
			rv.push($("tab-" + connection_id));
		} else {
			tabs = $("tabs-list").getElementsByTagName("li");
		}


		for (var i = 0; i < tabs.length; i++) {
			if (tabs[i].id && tabs[i].id.match("tab-")) {
				rv.push(tabs[i]);
			}
		}

		return rv;

	} catch (e) {
		exception_error("get_all_tabs", e);
	}
}

function update_buffer(force_redraw) {
	try {

		var tab = get_selected_tab();
		if (!tab) return;

		var channel = tab.getAttribute("channel");

		if (tab.getAttribute("tab_type") == "S") channel = "---";

		var connection_id = tab.getAttribute("connection_id");

		window.setTimeout(function() {
			$("log").scrollTop = $("log").scrollHeight;
		}, 100);

		update_title();

	} catch (e) {
		exception_error("update_buffer", e);
	}
}

function hide_topic_input() {
	try {
		Element.hide("topic-input-real");
		Element.show("topic-input");

	} catch (e) {
		exception_error("hide_topic_input", e);
	}
}

function prepare_change_topic(elem) {
	try {
		var tab = get_selected_tab();
		if (!tab || elem.hasClassName("disabled")) return;

		Element.hide("topic-input");
		Element.show("topic-input-real");

		$("topic-input-real").value = $("topic-input").title;
		$("topic-input-real").focus();

	} catch (e) {
		exception_error("change_topic", e);
	}
}

function change_topic_real(elem, evt) {
	try {
      var key;

		if (window.event)
			key = window.event.keyCode;     //IE
		else
			key = evt.which;     //firefox

		if (key == 13 && !Element.visible("topic-suggest")) {
			var tab = get_selected_tab();

			if (!tab || elem.disabled) return;

			var topic = elem.value;

			var channel = tab.getAttribute("channel");
			var connection_id = tab.getAttribute("connection_id")

			if (tab.getAttribute("tab_type") == "S") channel = "---";

			var chan = model.getChannel(connection_id, channel);

			if (chan)
				chan.topic(topic);

			//topics[connection_id][channel] = topic;

			var query = "op=set-topic&topic=" + param_escape(topic) +
				"&chan=" + param_escape(channel) +
				"&connection=" + param_escape(connection_id) +
				"&last_id=" + last_id;

			console.log(query);

			show_spinner();

			new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function (transport) {
				hide_spinner();
				handle_update(transport);
				elem.blur();
			} });
		}

	} catch (e) {
		exception_error("change_topic", e);
	}
}

function send(elem, evt) {
	try {

     var key;

		if(window.event)
			key = window.event.keyCode;     //IE
		else
			key = evt.which;     //firefox

		if (key == 13 && !Element.visible("input-suggest")) {

			var tab = get_selected_tab();

			if (!tab) return;

			var channel = tab.getAttribute("channel");

			if (tab.getAttribute("tab_type") == "S") channel = "---";

			if (elem.value.trim() == "/clear") {
				model.activeChannel().lines.removeAll();

			} else {
				var query = "op=send&message=" + param_escape(elem.value) +
					"&chan=" + param_escape(channel) +
					"&connection=" + param_escape(tab.getAttribute("connection_id")) +
					"&last_id=" + last_id + "&tab_type=" + tab.getAttribute("tab_type");

				show_spinner();

				new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function (transport) {
					hide_spinner();
					handle_update(transport);
				} });
			}

			push_cache(elem.value);
			elem.value = '';
			console.log(query);

			set_window_active(true);

			window.setTimeout(function() {
				elem.value = '';
			}, 5);

			return false;
		}

	} catch (e) {
		exception_error("send", e);
	}
}

function handle_error(obj, transport) {
	try {
		if (obj && obj.error) {
			return fatal_error(obj.error, obj.errormsg, transport.responseText);
		}
		return true;
	} catch (e) {
		exception_error("handle_error", e);
	}
}

function change_tab(elem) {
	try {

		if (!elem) return;

		console.log("changing tab to " + elem.id);

		if (!initial) {
			hash_set(elem.getAttribute("connection_id") + "," +
				elem.getAttribute("channel").replace("#", "#"));
		}

		model.activeChannel(elem.getAttribute("connection_id"), elem.getAttribute("channel"));

		update_buffer();

		if (theme != "tablet")
			$("input-prompt").focus();

	} catch (e) {
		exception_error("change_tab", e);
	}
}

function toggle_connection(connection_id, set_enabled) {
	try {

//		elem.disabled = true;

		var query = "op=toggle-connection&set_enabled=" + param_escape(set_enabled) +
			"&connection_id=" + param_escape(connection_id);

		console.log(query);

		show_spinner();

		new Ajax.Request("backend.php", {
		parameters: query,
		onComplete: function (transport) {
			hide_spinner();
		} });

	} catch (e) {
		exception_error("toggle_connection", e);
	}
}

function handle_conn_data(conndata) {
	try {
		if (conndata != "") {
			if (conndata.duplicate) return;

			valid_ids = [];

			for (var i = 0; i < conndata.length; i++) {

				var conn = model.getConnection(conndata[i].id);

				if (conn) {
					conn.update(conndata[i]);
				} else {
					model.connections.push(new Connection(conndata[i]));
				}

				valid_ids.push(conndata[i].id);

			}

			model.cleanupConnections(valid_ids);
		}
	} catch (e) {
		exception_error("handle_conn_data", e);
	}
}

function handle_chan_data(chandata) {
	try {
		if (chandata != "") {
			if (chandata.duplicate) return;

			for (var connection_id in chandata) {

				if (!model.getConnection(connection_id)) continue;

//				if (!topics[connection_id]) topics[connection_id] = [];

				var conn = model.getConnection(connection_id);

				var valid_channels = [];

				for (var chan in chandata[connection_id]) {

					var tab_type = "P";

					switch (parseInt(chandata[connection_id][chan].chan_type)) {
					case 0:
						tab_type = "C";
						break;
					case 1:
						tab_type = "P";
						break;
					}

					if (conn) {
						var channel = model.getChannel(connection_id, chan);

						if (channel) {
							channel.title(chan);
							channel.type(tab_type);
						} else {
							channel = new Channel(connection_id, chan, tab_type);
							conn.channels.push(channel);
						}

						channel.topic(chandata[connection_id][chan]["topic"]);

						conn.channels.sort(function(a, b) {
							return a.title().localeCompare(b.title());
						});

						if (tab_type == "C") {
							channel.nicklist(chandata[connection_id][chan]["users"]);
						} else if (tab_type == "P") {
							channel.nicklist(['@' + conn.active_nick()]);

							if (conn.nickExists(chan))
								channel.nicklist.push(chan);
						}

						valid_channels.push(chan);
					}
				}

				model.cleanupChannels(connection_id, valid_channels);
			}
		}

		update_title(chandata);

	} catch (e) {
		exception_error("handle_chan_data", e);
	}
}

function update_title() {
	try {

		var tab = get_selected_tab();

		if (tab) {
			var title = __("Tiny Tiny IRC [%a @ %b / %c]");
			var connection_id = tab.getAttribute("connection_id");

			if (!window_active && new_messages) {
				if (new_highlights) {
					title = "[*"+new_messages+"] " + title;
				} else {
					title = "["+new_messages+"] " + title;
				}

				if (window.fluid) {
					if (new_highlights) {
						window.fluid.dockBadge = "* " + new_messages;
					} else {
						window.fluid.dockBadge = new_messages;
					}
				}

				if ($("favicon").href.indexOf("active") == -1)
					$("favicon").href = $("favicon").href.replace("favicon",
							"favicon_active");

			} else {
				if (window.fluid) {
					window.fluid.dockBadge = "";
				}

				$("favicon").href = $("favicon").href.replace("favicon_active",
						"favicon");

			}


			if (model.getConnection(connection_id)) {
				title = title.replace("%a", model.getConnection(connection_id).active_nick());
				title = title.replace("%b", model.getConnection(connection_id).title());
				title = title.replace("%c", tab.getAttribute("channel"));
				document.title = title;
			} else {
				document.title = __("Tiny Tiny IRC");
			}

		} else {
			document.title = __("Tiny Tiny IRC");
		}

	} catch (e) {
		exception_error("update_title", e);
	}
}

function send_command(command) {
	try {

		var tab = get_selected_tab();

		if (tab) {

			var channel = tab.getAttribute("channel");

			if (tab.getAttribute("tab_type") == "S") channel = "---";

			var query = "op=send&message=" + param_escape(command) +
				"&channel=" + param_escape(channel) +
				"&connection=" + param_escape(tab.getAttribute("connection_id")) +
				"&last_id=" + last_id;

			console.log(query);

			show_spinner();

			new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function (transport) {
				hide_spinner();
				handle_update(transport);
			} });
		}

	} catch (e) {
		exception_error("send_command", e);
	}
}

function change_nick() {
	try {
		var nick = prompt("Enter new nickname:");

		if (nick) send_command("/nick " + nick);

	} catch (e) {
		exception_error("change_nick", e);
	}
}

function join_channel() {
	try {
		var channel = prompt("Channel to join:");

		if (channel) send_command("/join " + channel);

	} catch (e) {
		exception_error("join_channel", e);
	}
}

function handle_action(elem) {
	try {
		console.log("action: " + elem[elem.selectedIndex].value);

		elem.selectedIndex = 0;
	} catch (e) {
		exception_error("handle_action", e);
	}
}

function close_tab(elem) {
	try {

		if (!elem) return;

		var tab_id = elem.getAttribute("tab_id");
		var tab = $(tab_id);

		if (tab && confirm(__("Close this tab?"))) {

			var query = "op=part-channel" +
				"&chan=" + param_escape(tab.getAttribute("channel")) +
				"&connection=" + param_escape(tab.getAttribute("connection_id")) +
				"&last_id=" + last_id;

			console.log(query);

			show_spinner();

			new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function (transport) {
				handle_update(transport);
				hide_spinner();
			} });
		}

	} catch (e) {
		exception_error("close_tab", e);
	}
}

function query_user(elem) {
	try {

		if (!elem) return;

		var tab = get_selected_tab();
		var nick = elem.getAttribute("nick");
		var pr = __("Start conversation with %s?").replace("%s", nick);

		if (tab && confirm(pr)) {

			var query = "op=query-user&nick=" + param_escape(nick) +
				"&connection=" + param_escape(tab.getAttribute("connection_id")) +
				"&last_id=" + last_id;

			console.log(query);

			show_spinner();

			new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function (transport) {
				handle_update(transport);
				hide_spinner();
			} });

		}

	} catch (e) {
		exception_error("query_user", e);
	}
}

function handle_event(connection_id, line) {
	try {
		if (!line.message) return;

		var params = line.message.split(":", 3);

//		console.log("handle_event " + params[0]);

		switch (params[0]) {
		case "TOPIC":
			var topic = line.message.replace("TOPIC:", "");

			line.message = __("%u has changed the topic to: %s").replace("%u", line.sender);
			line.message = line.message.replace("%s", topic);
			line.sender = "---";

			push_message(connection_id, line.channel, line, MSGT_PRIVMSG);

			break;
		case "MODE":
			var mode = params[1];
			var subject = params[2];

			var msg_type;

			if (mode) {
				line.message = __("%u has changed mode [%m] on %s").replace("%u",
						line.sender);
				line.message = line.message.replace("%m", mode);
				line.message = line.message.replace("%s", subject);
				line.sender = "---";

				msg_type = MSGT_PRIVMSG;
			} else {
				line.sender = "---";

				line.message = __("%u has changed mode [%m]").replace("%u",
						line.channel);
				line.message = line.message.replace("%m", subject);

				msg_type = MSGT_BROADCAST;
			}

			push_message(connection_id, line.channel, line, msg_type, hide_join_part);

			break;
		case "KICK":
			var nick = params[1];
			var message = params[2];

			line.message = __("%u has been kicked from %c by %n (%m)").replace("%u", nick);
			line.message = line.message.replace("%c", line.channel);
			line.message = line.message.replace("%n", line.sender);
			line.message = line.message.replace("%m", message);
			line.sender = "---";

			push_message(connection_id, line.channel, line, MSGT_PRIVMSG);

			break;

		case "PART":
			var nick = params[1];
			var message = params[2];

			line.message = __("%u has left %c (%m)").replace("%u", nick);
			line.message = line.message.replace("%c", line.channel);
			line.message = line.message.replace("%m", message);

			push_message(connection_id, line.channel, line, MSGT_PRIVMSG, hide_join_part);

			break;
		case "JOIN":
			var nick = params[1];
			var host = params[2];

			line.message = __("%u (%h) has joined %c").replace("%u", nick);
			line.message = line.message.replace("%c", line.channel);
			line.message = line.message.replace("%h", host);
			line.message_type = MSGT_SYSTEM;

			push_message(connection_id, line.channel, line, MSGT_PRIVMSG, hide_join_part);

			break;
		case "QUIT":
			var quit_msg = line.message.replace("QUIT:", "");

			line.message = __("%u has quit IRC (%s)").replace("%u", line.sender);
			line.message = line.message.replace("%s", quit_msg);
			line.message_type = MSGT_SYSTEM;

			push_message(connection_id, line.channel, line, MSGT_PRIVMSG, hide_join_part);
			break;
		case "DISCONNECT":
			line.message = __("Connection terminated.");

			if (last_id > last_old_id && notify_events[3])
				notify("Disconnected from server.");

			push_message(connection_id, '---', line);
			break;
		case "REQUEST_CONNECTION":
			line.message = __("Requesting connection...");

			push_message(connection_id, '---', line);
			break;
		case "CONNECTING":
			var server = params[1];
			var port = params[2];

			line.message = __("Connecting to %s:%d...").replace("%s", server);
			line.message = line.message.replace("%d", port);

			push_message(connection_id, '---', line);
			break;
		case "PING_REPLY":
			var args = params[1];

			line.message = __("Ping reply from %u: %d second(s).").replace("%u",
					line.sender);
			line.message = line.message.replace("%d", args);
			line.message_type = MSGT_SYSTEM;

			var tab = get_selected_tab();

			if (!tab) get_all_tabs()[0];

			if (tab) {
				var chan = tab.getAttribute("channel");
				line.channel = chan;
			}

			push_message(connection_id, line.channel, line);
			break;
		case "NOTICE":
			var message = params[1];
			var tab = get_selected_tab();

			line.message = message;
			line.message_type = MSGT_NOTICE;

			if (line.channel != "---")
				push_message(connection_id, line.sender, line);
			else
				push_message(connection_id, line.channel, line);

			break;

		case "CTCP":
			var command = params[1];
			var args = params[2];

			line.message = __("Received CTCP %c (%a) from %u").replace("%c", command);
			line.message = line.message.replace("%a", args);
			line.message = line.message.replace("%u", line.sender);
			line.message_type = MSGT_SYSTEM;

			push_message(connection_id, '---', line);
			break;

		case "CTCP_REPLY":
			var command = params[1];
			var args = params[2];

			line.message = __("CTCP %c reply from %u: %a").replace("%c", command);
			line.message = line.message.replace("%a", args);
			line.message = line.message.replace("%u", line.sender);
			line.message_type = MSGT_SYSTEM;

			push_message(connection_id, line.channel, line);
			break;


		case "PING":
			var args = params[1];

			line.message = __("Received ping (%s) from %u").replace("%s", args);
			line.message = line.message.replace("%u", line.sender);
			line.message_type = MSGT_SYSTEM;

			push_message(connection_id, '---', line, MSGT_BROADCAST);
			break;
		case "CONNECT":
			line.message = __("Connection established.");

			if (last_id > last_old_id && notify_events[3])
				notify("Connected to server.");

			push_message(connection_id, '---', line);
			break;
		case "UNKNOWN_CMD":
			line.message = __("Unknown command: /%s.").replace("%s", params[1]);
			push_message(connection_id, "---", line, MSGT_PRIVMSG);
			break;
		case "NICK":
			var new_nick = params[1];

			var chan = model.getChannel(connection_id, line.sender);

			if (chan) chan.title(new_nick);

			line.message = __("%u is now known as %n").replace("%u", line.sender);
			line.message = line.message.replace("%n", new_nick);
			line.message_type = MSGT_SYSTEM;

			push_message(connection_id, line.channel, line, MSGT_PRIVMSG, hide_join_part);

			break;

		}

	} catch (e) {
		exception_error("handle_event", e);
	}
}

function push_message(connection_id, channel, message, message_type, no_tab_hl) {
	try {
		if (!model.getConnection(connection_id)) return;

		if (no_tab_hl == undefined) no_tab_hl = false;

		if (!message_type) message_type = MSGT_PRIVMSG;

		if (id_history.indexOf(message.id) != -1 && !message.force_display)
			return; // dupe

		id_history.push(message.id);

		while (id_history.length > 20)
			id_history.shift();

		if (message_type != MSGT_BROADCAST) {
			var chan = model.getChannel(connection_id, channel);

			if (chan && chan.lines) {

				while (chan.lines.length > 5)
					chan.lines.shift();

				chan.lines.push(new Message(message));
			}

			var tab = find_tab(connection_id, channel);

			if (!no_tab_hl && tab && (get_selected_tab() != tab || !window_active)) {

				if (notify_events[1]) {
					var msg = __("(%c) %n: %s");

					msg = msg.replace("%c", message.channel);
					msg = msg.replace("%n", message.sender);
					msg = msg.replace("%s", message.message);

					if (message.sender && message.channel &&
							message.sender != model.getConnection(connection_id).active_nick()) {

						notify(msg);
					}
				}

				if (notify_events[2] && tab.getAttribute("tab_type") == "P" && message.id > last_old_id) {
					var msg = __("%n: %s");

					msg = msg.replace("%n", message.sender);
					msg = msg.replace("%s", message.message);

					if (message.sender && message.sender != model.getConnection(connection_id).active_nick()) {
						notify(msg);
					}

				}

				if (message_type == MSGT_PRIVMSG && !no_tab_hl && notify_events[4] && tab.getAttribute("tab_type") == "C" && message.id > last_old_id) {
					var msg = __("(%c) %n: %s");

					msg = msg.replace("%n", message.sender);
					msg = msg.replace("%s", message.message);
					msg = msg.replace("%c", message.channel);

					if (message.sender && message.channel &&
							message.sender != model.getConnection(connection_id).active_nick()) {

						notify(msg);
					}
				}

			}

			if (!no_tab_hl && message.ts > startup_date)
				highlight_tab_if_needed(connection_id, channel, message);

		} else {
			var tabs = get_all_tabs(connection_id);

			for (var i = 0; i < tabs.length; i++) {

				if (tabs[i].getAttribute("tab_type") == "C") {
					var chan = model.getChannel(connection_id, tabs[i].getAttribute("channel"));

					if (chan && chan.lines) {
						chan.lines.push(new Message(message));
					}
				}
			}
		}

	} catch (e) {
		exception_error("push_message", e);
	}
}

function set_window_active(active) {
	try {
		console.log("set_window_active: " + active);

		window_active = active;

		if (active) {
			new_messages = 0;
			new_highlights = 0;

			while (notifications.length > 0) {
				notifications.pop().cancel();
			}

			window.setTimeout(function() {
				$("log").scrollTop = $("log").scrollHeight;
			}, 100);

			if (theme != "tablet")
				$("input-prompt").focus();
		}

		window.setTimeout("update_title()", 100);
	} catch (e) {
		exception_error("window_active", e);
	}
}

function resize_preview() {

	try {
		var vp = document.viewport.getDimensions();
		var img = $$("#image-preview img")[0];

		var max_width = vp.width/1.5;
		var max_height = vp.height/1.5;

		if (img.width > max_width) {
			img.height *= (max_width / img.width);
			img.width = max_width;
		}

		if (img.height > max_height) {
			img.width *= (max_height / img.height);
			img.height = max_height;
		}

		var dp = $("image-preview").getDimensions();

		$("image-preview").setStyle({
			left: (vp.width/2 - dp.width/2) + "px",
			top: (vp.height/2 - dp.height/2) + "px",
			width: dp.width,
			height: dp.height,
		});

	} catch (e) {
		exception_error("resize_preview", e);
	}
}

function show_preview(img) {
	try {
		hide_spinner();

		Element.show("image-preview");

		window.setTimeout("resize_preview()", 1);

	} catch (e) {
		exception_error("show_preview", e);
	}
}

function url_clicked(elem) {
	try {
		if (navigator.userAgent && navigator.userAgent.match("MSIE"))
			return true;

		if (!elem.href.toLowerCase().match("(jpg|gif|png|bmp)$"))
			return true;

		window.clearTimeout(elem.getAttribute("timeout"));

		show_spinner();

		$("image-preview").innerHTML = "<img onload=\"show_preview(this)\" " +
			"src=\"" + elem.href + "\"/>";

		return false;

	} catch (e) {
		exception_error("url_clicked", e);
	}
}

function hotkey_handler(e) {

	try {

		var keycode;
		var shift_key = false;

		var cmdline = $('cmdline');
		var feedlist = $('feedList');

		try {
			shift_key = e.shiftKey;
		} catch (e) {

		}

		if (window.event) {
			keycode = window.event.keyCode;
		} else if (e) {
			keycode = e.which;
		}

		var keychar = String.fromCharCode(keycode);

		if (keycode == 27) { // escape
			close_infobox();
			Element.hide("image-preview");
		}

		if (!hotkeys_enabled) {
			console.log("hotkeys disabled");
			return;
		}

		if (keycode == 38 && e.ctrlKey) {
			console.log("moving up...");

			var tabs = get_all_tabs();
			var tab = get_selected_tab();

			if (tab) {
				for (var i = 0; i < tabs.length; i++) {
					if (tabs[i] == tab) {
						change_tab(tabs[i-1]);
						return false;
					}
				}
			}

			return false;
		}

		if (keycode == 40 && e.ctrlKey) {
			console.log("moving down...");

			var tabs = get_all_tabs();
			var tab = get_selected_tab();

			if (tab) {
				for (var i = 0; i < tabs.length; i++) {
					if (tabs[i] == tab) {
						change_tab(tabs[i+1]);
						return false;
					}
				}
			}

			return false;
		}

		if (keycode == 38) {
			var elem = $("input-prompt");

			if (input_cache_offset > -input_cache.length)
				--input_cache_offset;

			var real_offset = input_cache.length + input_cache_offset;

			if (input_cache[real_offset]) {
				elem.value = input_cache[real_offset];
				elem.setSelectionRange(elem.value.length, elem.value.length);
			}

//			console.log(input_cache_offset + " " + real_offset);

			return false;
		}

		if (keycode == 40) {
			var elem = $("input-prompt");

			if (input_cache_offset < -1) {
			  	++input_cache_offset;

				var real_offset = input_cache.length + input_cache_offset;

//				console.log(input_cache_offset + " " + real_offset);

				if (input_cache[real_offset]) {
					elem.value = input_cache[real_offset];
					elem.setSelectionRange(elem.value.length, elem.value.length);
					return false;
				}

			} else {
				elem.value = '';
				input_cache_offset = 0;
				return false;
			}

		}

		if (keycode == 76 && e.ctrlKey) {
			if (model.activeChannel())
				model.activeChannel().lines.removeAll();

			return false;
		}

		if (keycode == 9) {
			$("input-prompt").focus();
			return false;
		}

		return true;

	} catch (e) {
		exception_error("hotkey_handler", e);
	}
}

function push_cache(line) {
	try {
//		line = line.trim();

		input_cache_offset = 0;

		if (line.length == 0) return;

		for (var i = 0; i < input_cache.length; i++) {
			if (input_cache[i] == line) return;
		}

		input_cache.push(line);

		while (input_cache.length > 100)
			input_cache.shift();

	} catch (e) {
		exception_error("push_cache", e);
	}
}

/* function get_nick_list(connection_id, channel) {
	try {
		var rv = [];

		if (nicklists[connection_id]) {

			var nicklist = nicklists[connection_id][channel];

			if (nicklist) {

				for (var i = 0; i < nicklist.length; i++) {

					var nick = nicklist[i];

					switch (nick.substr(0,1)) {
					case "@":
						nick = nick.substr(1);
						break;
					case "+":
						nick = nick.substr(1);
						break;
					}

					rv.push(nick);
				}
			}
		}

		return rv;

	} catch (e) {
		exception_error("get_nick_list", e);
	}
} */

function is_highlight(connection_id, message) {
	try {
		var message_text = message.message.toUpperCase();

		if (message.message_type == MSGT_SYSTEM)
			return false;

		if (message.sender == "---" || message.sender == model.getConnection(connection_id).active_nick())
			return false;

		if (message.id <= last_old_id)
			return false;

		if (message_text.match(":\/\/"))
			return false;

		if (model.getConnection(connection_id) &&
				message_text.match(model.getConnection(connection_id).active_nick().toUpperCase()))
			return true;

		for (var i = 0; i < highlight_on.length; i++) {
			if (highlight_on[i].length > 0 && message_text.match(highlight_on[i]))
				return true;
		}

		return false;

	} catch (e) {
		exception_error("is_highlight", e);
	}
}

function highlight_tab_if_needed(connection_id, channel, message) {
	try {
		console.log("highlight_tab_if_needed " + connection_id + " " + channel);

		var chan = model.getChannel(connection_id, channel);

		if (chan && chan != model.activeChannel()) {
			if (chan.type() != "S" && is_highlight(connection_id, message)) {
				chan.highlight(true);
				++new_highlights;
			} else {
				chan.attention(true);
			}
		}

	} catch (e) {
		exception_error("highlight_tab_if_needed", e);
	}
}

function find_tab(connection_id, channel) {
	try {
		var tabs;

//		console.log("find_tab : " + connection_id + ";" + channel);

		if (channel == "---") {
			return $("tab-" + connection_id);
		} else {
			if (connection_id) {
				tabs = $("tabs-" + connection_id).getElementsByTagName("LI");
			} else {
				tabs = $("tabs-list").getElementsByTagName("li");
			}

			for (var i = 0; i < tabs.length; i++) {
				if (tabs[i].id && tabs[i].id.match("tab-")) {
					if (tabs[i].getAttribute("channel") == channel) {
						return tabs[i];
					}
				}
			}
		}

		return false;

	} catch (e) {
		exception_error("find_tab", e);
	}
}

function title_timeout() {
	try {
		update_title();
		window.setTimeout('title_timeout()', 2000);
	} catch (e) {
		exception_error("title_timeout", e);
	}
}

function inject_text(str) {
	try {
		$("input-prompt").value += " " + str + " ";
		$("input-prompt").focus();

	} catch (e) {
		exception_error("inject_text", e);
	}
}

function rewrite_emoticons(str) {
	try {
		if (!str) return "";

		if (emoticons_map && get_cookie('ttirc_emoticons') != "false") {
			for (key in emoticons_map) {
				str = str.replace(
						new RegExp(RegExp.escape(key), "g"),
					"<img title=\""+key+"\" class=\"anim\" src=\"emoticons/"+emoticons_map[key][0]+"\" "+
					" height=\""+emoticons_map[key][1]+"\">");
			}
		}

		str = str.replace(/\(тм\)|\(tm\)/g, "&trade;");
		str = str.replace(/\(р\)|\(r\)/g, "&reg;");
		str = str.replace(/\(ц\)|\(с\)|\(c\)/g, "&copy;");

		str = str.replace(/(=\)|8\)|8\(\))|[-\\\\^]_{1,5}[-\\\\^]|lol|лол|kjk|кжк/g,
				"<span class='anim'>$&</span>");

		str = str.replace(/([=8:;]\(|[T]_{1,5}[T])/,
				"<span class='anim blue'>$&</span>");

		ts = new Date().getTime();

		str = str.replace(/[АF!]{3,}/g,
				"<span applied_at='"+ts+"' class='ahl applied'>$&</span>");

		return str;

	} catch (e) {
		exception_error("rewrite_emoticons", e);
	}
}

function hash_get() {
	try {
		return decodeURIComponent(window.location.hash.substring(1));
	} catch (e) {
		exception_error("hash_get", e);
	}
}
function hash_set(value) {
	try {
		window.location.hash = param_escape(value);
	} catch (e) {
		exception_error("hash_set", e);
	}
}
