package org.fox.ttirc;

import java.sql.PreparedStatement;
import java.sql.SQLException;
import java.util.*;
import org.json.simple.*;

public class NickList {
	
	public class NickComparator implements Comparator<String> {

		public int compare(String o1, String o2) {
			char c1 = o1.charAt(0);
			char c2 = o2.charAt(0);
			
			if (c1 == '@' && c2 != '@') {
				return 0;
			}

			if (c1 != '@' && c2 == '@') {
				return 1;
			}

			if (c1 == '+' && c2 != '+') {
				return 0;
			}

			if (c1 != '+' && c2 == '+') {
				return 1;
			}

			return o1.compareTo(o2);
		}

	}

	public class Nick {
		private String nick;
		private boolean v = false;
		private boolean o = false;
				
		public Nick(String nick) {
			
			if (nick.charAt(0) == '@') {
				nick = nick.substring(1);
				o = true;
			} else if (nick.charAt(0) == '+') {
				nick = nick.substring(1);
				v = true;
			}
		
			this.nick = nick;		
		}
		
		public Nick(String nick, boolean v, boolean o) {
			this.nick = nick;
			this.v = v;
			this.o = o;
		}
		
		public void renameTo(String nick) {
			this.nick = nick;
		}
		
		public void setVoiced(boolean v) {
			this.v = v;
		}
		
		public void setOp(boolean o) {
			this.o = o;
		}
		
		public boolean isVoiced() {
			return v;
		}
		
		public boolean isOp() {
			return o;
		}
		
		public String stripPrefix(String nick) {
			if (nick.charAt(0) == '@') {
				nick = nick.substring(1);
			} else if (nick.charAt(0) == '+') {
				nick = nick.substring(1);
			}
			return nick;
		}
		
		public boolean equals(Object obj) {
			boolean result = this.nick.equalsIgnoreCase(stripPrefix(obj.toString()));
			
			return result;
		}
		
		public String toString() {
			String prefix = "";
			
			if (o) 
				prefix += "@";			
			else if (v) 
				prefix += "+";
			
			return prefix + nick;
		}
		
		public int hashCode() {
			return nick.hashCode();
		}

		public void updateModes(String nick) {
			if (nick.charAt(0) == '@') {
				o = true;
			} else if (nick.charAt(0) == '+') {
				v = true;
			}			
		}

		/* returns nick without mode prefixes */		
		public String getNick() {
			return nick;
		}
	}

	private Hashtable<String, Vector<Nick>> nicklist = new Hashtable<String, Vector<Nick>>();
	private NativeConnectionHandler handler;
	
	public NickList(NativeConnectionHandler handler) {
		this.handler = handler;  
	}
	
	public void addNick(String chan, String nick) {
		
		if (!nicklist.containsKey(chan)) 
			nicklist.put(chan, new Vector<Nick>());
	
		//Nick n = new Nick(nick);
		Nick n = findNick(chan, nick);
		
		if (n != null) {
			n.updateModes(nick);			
		} else {
			n = new Nick(nick);
			nicklist.get(chan).add(n);
			
			handler.requestUserhost(n);
			
			//System.out.println("Added " + nick + " on " + chan);
		}
		
		Sync(chan);
	}
	
	public void removeNick(String chan, String nick) {
		if (!nicklist.containsKey(chan)) 
			nicklist.put(chan, new Vector<Nick>());

		Nick n = new Nick(nick); 
		
		nicklist.get(chan).remove(n);
		
		if (numChans(nick) == 0)
			handler.removeUserhost(nick);
		
		Sync(chan);
	}

	public void removeNick(String nick) {

		Enumeration<String> chans = nicklist.keys();
		
		Nick n = new Nick(nick);
		
		while (chans.hasMoreElements()) {
			String chan = chans.nextElement();
			
			nicklist.get(chan).remove(n);		
		}

		handler.removeUserhost(nick);

		Sync();
	}
	
	public int numChans(String nick) {
		Enumeration<String> chans = nicklist.keys();
		int rv = 0;
		Nick n = new Nick(nick);
		
		while (chans.hasMoreElements()) {
			String chan = chans.nextElement();
			if (nicklist.get(chan).contains(n)) {
				++rv;
			}
		}
	
		return rv;
		
	}
	
	public Vector<String> isOn(String nick) {
		
		Vector<String> tmp = new Vector<String>();
		Enumeration<String> chans = nicklist.keys();
		
		Nick n = new Nick(nick);
		
		while (chans.hasMoreElements()) {
			String chan = chans.nextElement();
			if (nicklist.get(chan).contains(n)) {
				tmp.add(chan);
			}
		}
	
		return tmp;
	}
	
	public void Sync() {
		Enumeration<String> en = nicklist.keys();
		
		while (en.hasMoreElements()) {
			String chan = en.nextElement();				
			Sync(chan);
		}		
	}

	public Nick findNick(String channel, String nick) {
		Enumeration<Nick> nicks = nicklist.get(channel).elements();
		
		while (nicks.hasMoreElements()) {
			Nick n = nicks.nextElement();
			
			if (n.equals(nick)) return n;
		}
		
		return null;
	}
	
	public boolean setVoiced(String channel, String nick, boolean v) {
		Nick n = findNick(channel, nick);
		
		//System.err.println("setVoiced " + channel + " " + nick + " = " + v + "; " + n);
		
		if (n != null) {
			n.setVoiced(v);
			Sync(channel);
			return true;
		}		
		return false;
	}

	public boolean setOp(String channel, String nick, boolean o) {
		Nick n = findNick(channel, nick);
		
		if (n != null) {
			n.setOp(o);
			Sync(channel);
			return true;
		}		
		return false;
	}

	public void renameNick(String oldNick, String newNick) {
		Enumeration<String> chans = nicklist.keys();
		
		while (chans.hasMoreElements()) {
			String chan = chans.nextElement();
			
			Enumeration<Nick> nicks = nicklist.get(chan).elements();
				
			while (nicks.hasMoreElements()) {
				Nick nick = nicks.nextElement();
			
				if (nick.equals(oldNick)) {				
					nick.renameTo(newNick);		
					handler.renameUserhost(oldNick, newNick);
					handler.pushMessage(oldNick, chan, "NICK:" + newNick, Constants.MSGT_EVENT);
					handler.renamePrivateChannel(oldNick, newNick);
				}
			}		
		}
		
		Sync();
	}
	
	@SuppressWarnings("unchecked")
	public void Sync(String channel)  {
		
		Enumeration<Nick> en = this.nicklist.get(channel).elements();

		JSONArray nicks = new JSONArray();

		while (en.hasMoreElements()) {
			Nick n = en.nextElement();
			nicks.add(n.toString());				
		}

		//Collections.sort(nicks, new NickComparator());
		
		try {
		
			PreparedStatement ps = handler.getConnection().prepareStatement("UPDATE ttirc_channels " +
				"SET nicklist = ? WHERE channel = ? AND connection_id = ?");
			
			ps.setString(1, nicks.toJSONString());
			ps.setString(2, channel);
			ps.setInt(3, handler.getConnectionId());
			ps.execute();
			ps.close();
			
		} catch (SQLException e) {
			e.printStackTrace();
		}
		
	}

	public Vector<Nick> getNicks(String chan) {
		return nicklist.get(chan);
	}
	
	public void removeChannel(String chan) {
		Enumeration<Nick> en = nicklist.get(chan).elements();
		
		while (en.hasMoreElements()) {
			Nick n = en.nextElement();
			
			if (numChans(n.getNick()) == 1)
				handler.removeUserhost(n.getNick());
		}
		
		nicklist.remove(chan);		
	}
}
