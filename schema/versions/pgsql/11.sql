begin;

create table ttirc_emoticons_popcon (id serial not null primary key,
	owner_uid integer not null references ttirc_users(id) on delete cascade,
	emoticon varchar(60) not null,
	last_ised timestamp not null default NOW(),
	times_used integer not null default 0);

update ttirc_version set schema_version = 11;

commit;
