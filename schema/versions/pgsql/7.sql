begin;

alter table ttirc_users add column salt varchar(250);
update ttirc_users set salt = '';
alter table ttirc_users alter column salt set not null;
alter table ttirc_users alter column salt set default '';

update ttirc_version set schema_version = 7;

commit;
