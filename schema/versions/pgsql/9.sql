begin;

insert into ttirc_prefs (pref_name,type_id,def_value,short_desc,section_id) values('DISABLE_IMAGE_PREVIEW', 1, 'false', '', 1);

update ttirc_version set schema_version = 9;

commit;
