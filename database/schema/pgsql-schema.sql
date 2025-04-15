--
-- PostgreSQL database dump
--

-- Dumped from database version 17.4 (Debian 17.4-1.pgdg120+2)
-- Dumped by pg_dump version 17.4 (Debian 17.4-1.pgdg120+2)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: citext; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS citext WITH SCHEMA public;


--
-- Name: EXTENSION citext; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION citext IS 'data type for case-insensitive character strings';


--
-- Name: pg_stat_statements; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_stat_statements WITH SCHEMA public;


--
-- Name: EXTENSION pg_stat_statements; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pg_stat_statements IS 'track planning and execution statistics of all SQL statements executed';


--
-- Name: update_game_slug(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.update_game_slug() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            DECLARE
                base_slug TEXT;
                new_slug TEXT;
                counter INTEGER;
            BEGIN
                IF NEW.is_visible = true AND (OLD.is_visible = false OR OLD.is_visible IS NULL) AND
                   (NEW.slug IS NULL OR NEW.slug = '') THEN
                    -- Create base slug from name
                    base_slug := LOWER(REGEXP_REPLACE(NEW.name, '[^a-zA-Z0-9]+', '-', 'g'));
                    -- Remove leading/trailing hyphens
                    base_slug := TRIM(BOTH '-' FROM base_slug);

                    -- Start with base slug
                    new_slug := base_slug;
                    counter := 1;

                    -- Keep trying with incrementing numbers until we find a unique slug
                    WHILE EXISTS (
                        SELECT 1 FROM games
                        WHERE slug = new_slug
                        AND id != NEW.id
                    ) LOOP
                        new_slug := base_slug || '-' || counter;
                        counter := counter + 1;
                    END LOOP;

                    NEW.slug := new_slug;
                END IF;
                RETURN NEW;
            END;
            $$;


--
-- Name: update_game_version_latest_flag(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.update_game_version_latest_flag() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            DECLARE
                current_latest_id bigint;
                new_latest_id bigint;
            BEGIN
                -- For INSERT or UPDATE
                IF (TG_OP = 'INSERT') OR (TG_OP = 'UPDATE' AND OLD.published_at <> NEW.published_at) THEN
                    -- Get current latest version for this game
                    SELECT id INTO current_latest_id
                    FROM game_versions
                    WHERE game_id = NEW.game_id AND is_latest = true;

                    -- Find what should be the latest version
                    SELECT id INTO new_latest_id
                    FROM game_versions
                    WHERE game_id = NEW.game_id
                    ORDER BY published_at DESC
                    LIMIT 1;

                    -- Only update if there's a change in which version is latest
                    IF COALESCE(current_latest_id, 0) <> new_latest_id THEN
                        -- Set is_latest=false for old latest
                        IF current_latest_id IS NOT NULL THEN
                            UPDATE game_versions
                            SET is_latest = false
                            WHERE id = current_latest_id;
                        END IF;

                        -- Set is_latest=true for new latest
                        UPDATE game_versions
                        SET is_latest = true
                        WHERE id = new_latest_id;
                    END IF;
                -- For DELETE
                ELSIF TG_OP = 'DELETE' THEN
                    -- Only proceed if we're deleting a latest version
                    IF OLD.is_latest THEN
                        -- Find new latest version
                        SELECT id INTO new_latest_id
                        FROM game_versions
                        WHERE game_id = OLD.game_id
                        ORDER BY published_at DESC
                        LIMIT 1;

                        -- Set new latest version if one exists
                        IF new_latest_id IS NOT NULL THEN
                            UPDATE game_versions
                            SET is_latest = true
                            WHERE id = new_latest_id;
                        END IF;
                    END IF;
                END IF;

                RETURN NULL;
            END;
            $$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: version_character_stats; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_character_stats (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    game_version_id bigint NOT NULL,
    character_id bigint NOT NULL,
    iso_code character varying(10) NOT NULL,
    blocks integer DEFAULT 0 NOT NULL,
    words integer DEFAULT 0 NOT NULL
);


--
-- Name: character_version_stats_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.character_version_stats_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: character_version_stats_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.character_version_stats_id_seq OWNED BY public.version_character_stats.id;


--
-- Name: characters; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.characters (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    game_id bigint NOT NULL,
    character_id character varying(50) NOT NULL,
    display_names jsonb NOT NULL,
    first_seen_in_version_id bigint,
    last_seen_in_version_id bigint,
    display_name_corrections jsonb
);


--
-- Name: characters_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.characters_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: characters_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.characters_id_seq OWNED BY public.characters.id;


--
-- Name: discord_users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.discord_users (
    id bigint NOT NULL,
    discord_id character varying(100),
    processed_at timestamp without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: game_game_jam; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.game_game_jam (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    game_id bigint NOT NULL,
    game_jam_id bigint NOT NULL,
    ranking character varying(255),
    criteria_rankings jsonb
);


--
-- Name: game_game_jam_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.game_game_jam_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: game_game_jam_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.game_game_jam_id_seq OWNED BY public.game_game_jam.id;


--
-- Name: game_jams; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.game_jams (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    name character varying(255) NOT NULL,
    url character varying(255) NOT NULL,
    description text,
    start_date timestamp(0) without time zone,
    end_date timestamp(0) without time zone,
    submission_count integer,
    participant_count integer,
    host character varying(255),
    needs_details_fetch boolean DEFAULT true NOT NULL
);


--
-- Name: game_jams_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.game_jams_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: game_jams_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.game_jams_id_seq OWNED BY public.game_jams.id;


--
-- Name: game_versions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.game_versions (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    published_at timestamp(0) without time zone NOT NULL,
    game_id bigint NOT NULL,
    version character varying(20) NOT NULL,
    devlog character varying(250),
    is_windows boolean DEFAULT false NOT NULL,
    is_linux boolean DEFAULT false NOT NULL,
    is_mac boolean DEFAULT false NOT NULL,
    is_android boolean DEFAULT false NOT NULL,
    is_web boolean DEFAULT false NOT NULL,
    rating double precision,
    rating_count integer,
    is_latest boolean DEFAULT false NOT NULL
);


--
-- Name: game_versions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.game_versions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: game_versions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.game_versions_id_seq OWNED BY public.game_versions.id;


--
-- Name: games; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.games (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    initially_published_at timestamp(0) without time zone,
    game_id integer NOT NULL,
    name public.citext NOT NULL,
    status character varying(50) DEFAULT 'In development'::character varying NOT NULL,
    is_visible boolean DEFAULT false NOT NULL,
    is_nsfw boolean DEFAULT false NOT NULL,
    description text,
    url character varying(255) NOT NULL,
    thumb_url character varying(255),
    tags character varying(255),
    game_engine character varying(50) DEFAULT 'unknown'::character varying NOT NULL,
    error text,
    authors public.citext,
    custom_tags public.citext DEFAULT ''::public.citext NOT NULL,
    uploads jsonb DEFAULT '{}'::jsonb,
    is_feedless boolean DEFAULT false NOT NULL,
    slug public.citext,
    source_language_id character varying(3),
    optimized_thumbnails jsonb,
    average_score numeric(8,4),
    rating_count integer DEFAULT 0 NOT NULL,
    min_price numeric(10,2),
    is_on_sale boolean DEFAULT false NOT NULL,
    screenshots jsonb,
    full_description text,
    custom_css text,
    is_paid boolean DEFAULT false NOT NULL,
    has_demo boolean DEFAULT false NOT NULL
);


--
-- Name: games_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.games_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: games_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.games_id_seq OWNED BY public.games.id;


--
-- Name: import_states; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.import_states (
    id bigint NOT NULL,
    type character varying(255) NOT NULL,
    last_processed_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: import_states_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.import_states_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: import_states_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.import_states_id_seq OWNED BY public.import_states.id;


--
-- Name: iso_639_3_languages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.iso_639_3_languages (
    id character varying(3) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    part2b character varying(3),
    part2t character varying(3),
    part1 character varying(2),
    scope character varying(1) NOT NULL,
    type character varying(1) NOT NULL,
    ref_name character varying(150) NOT NULL,
    comment character varying(150),
    flag_code character varying(2) NOT NULL
);


--
-- Name: language_mappings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.language_mappings (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    game_language_key character varying(50) NOT NULL,
    iso_code character varying(3) NOT NULL,
    game_id bigint
);


--
-- Name: language_mappings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.language_mappings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: language_mappings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.language_mappings_id_seq OWNED BY public.language_mappings.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: monitored_scheduled_task_log_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.monitored_scheduled_task_log_items (
    id bigint NOT NULL,
    monitored_scheduled_task_id bigint NOT NULL,
    type character varying(255) NOT NULL,
    meta json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: monitored_scheduled_task_log_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.monitored_scheduled_task_log_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: monitored_scheduled_task_log_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.monitored_scheduled_task_log_items_id_seq OWNED BY public.monitored_scheduled_task_log_items.id;


--
-- Name: monitored_scheduled_tasks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.monitored_scheduled_tasks (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    type character varying(255),
    cron_expression character varying(255) NOT NULL,
    timezone character varying(255),
    ping_url character varying(255),
    last_started_at timestamp(0) without time zone,
    last_finished_at timestamp(0) without time zone,
    last_failed_at timestamp(0) without time zone,
    last_skipped_at timestamp(0) without time zone,
    registered_on_oh_dear_at timestamp(0) without time zone,
    last_pinged_at timestamp(0) without time zone,
    grace_time_in_minutes integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: monitored_scheduled_tasks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.monitored_scheduled_tasks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: monitored_scheduled_tasks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.monitored_scheduled_tasks_id_seq OWNED BY public.monitored_scheduled_tasks.id;


--
-- Name: notification_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notification_history (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    game_id bigint NOT NULL,
    game_version_id bigint NOT NULL,
    type character varying(255) NOT NULL,
    success boolean DEFAULT true NOT NULL,
    meta_data text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT notification_history_type_check CHECK (((type)::text = ANY (ARRAY[('discord'::character varying)::text, ('telegram'::character varying)::text, ('email'::character varying)::text, ('browser'::character varying)::text])))
);


--
-- Name: notification_history_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.notification_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: notification_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.notification_history_id_seq OWNED BY public.notification_history.id;


--
-- Name: notification_queue; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notification_queue (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    game_id bigint NOT NULL,
    game_version_id bigint NOT NULL,
    channel character varying(255) NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    scheduled_at timestamp(0) without time zone,
    processed_at timestamp(0) without time zone,
    payload json,
    error text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    meta_data jsonb,
    CONSTRAINT notification_queue_channel_check CHECK (((channel)::text = ANY (ARRAY[('browser'::character varying)::text, ('discord'::character varying)::text, ('email'::character varying)::text])))
);


--
-- Name: notification_queue_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.notification_queue_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: notification_queue_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.notification_queue_id_seq OWNED BY public.notification_queue.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email public.citext NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: processed_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.processed_events (
    id integer NOT NULL,
    event_id integer NOT NULL,
    game_id integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: processed_events_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.processed_events_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: processed_events_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.processed_events_id_seq OWNED BY public.processed_events.id;


--
-- Name: push_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.push_subscriptions (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    endpoint character varying(500) NOT NULL,
    p256dh character varying(255) NOT NULL,
    auth character varying(255) NOT NULL,
    subscription_data json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: push_subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.push_subscriptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: push_subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.push_subscriptions_id_seq OWNED BY public.push_subscriptions.id;


--
-- Name: raters; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.raters (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    user_id integer NOT NULL,
    name public.citext NOT NULL,
    username public.citext,
    alias character varying(255),
    is_suspicious boolean DEFAULT false NOT NULL,
    suspicion_reason character varying(255),
    marked_suspicious_at timestamp(0) without time zone
);


--
-- Name: raters_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.raters_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: raters_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.raters_id_seq OWNED BY public.raters.id;


--
-- Name: ratings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ratings (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    published_at timestamp(0) without time zone NOT NULL,
    event_id bigint NOT NULL,
    game_id bigint NOT NULL,
    rater_id bigint NOT NULL,
    rating smallint NOT NULL,
    review text NOT NULL,
    is_visible boolean DEFAULT true NOT NULL,
    is_reviewed boolean DEFAULT false NOT NULL
);


--
-- Name: ratings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ratings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ratings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ratings_id_seq OWNED BY public.ratings.id;


--
-- Name: social_accounts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.social_accounts (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    provider_name character varying(255) NOT NULL,
    provider_id character varying(255) NOT NULL,
    token character varying(255),
    refresh_token character varying(255),
    token_expires_at timestamp(0) without time zone,
    provider_data json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: social_accounts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.social_accounts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: social_accounts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.social_accounts_id_seq OWNED BY public.social_accounts.id;


--
-- Name: unique_dialogue_texts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.unique_dialogue_texts (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    text_hash character varying(32) NOT NULL,
    text_content text NOT NULL,
    search_vector tsvector GENERATED ALWAYS AS (to_tsvector('english'::regconfig, text_content)) STORED NOT NULL
);


--
-- Name: COLUMN unique_dialogue_texts.text_hash; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.unique_dialogue_texts.text_hash IS 'MD5 hash of the text for quick lookups';


--
-- Name: unique_dialogue_texts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.unique_dialogue_texts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: unique_dialogue_texts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.unique_dialogue_texts_id_seq OWNED BY public.unique_dialogue_texts.id;


--
-- Name: user_game_progress; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_game_progress (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    game_id bigint NOT NULL,
    game_version_id bigint,
    started_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    personal_notes text,
    status character varying(255) DEFAULT 'reading'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    receive_updates boolean DEFAULT false NOT NULL,
    CONSTRAINT user_game_progress_status_check CHECK (((status)::text = ANY (ARRAY[('reading'::character varying)::text, ('completed'::character varying)::text, ('plan_to_read'::character varying)::text, ('on_hold'::character varying)::text, ('dropped'::character varying)::text, ('custom'::character varying)::text])))
);


--
-- Name: user_game_progress_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_game_progress_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_game_progress_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_game_progress_id_seq OWNED BY public.user_game_progress.id;


--
-- Name: user_notification_preferences; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_notification_preferences (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    discord_notifications_enabled boolean DEFAULT false NOT NULL,
    browser_notifications_enabled boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    notification_digest character varying(255) DEFAULT 'asap'::character varying NOT NULL,
    CONSTRAINT user_notification_preferences_notification_digest_check CHECK (((notification_digest)::text = ANY (ARRAY[('asap'::character varying)::text, ('daily'::character varying)::text, ('weekly'::character varying)::text])))
);


--
-- Name: user_notification_preferences_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_notification_preferences_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_notification_preferences_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_notification_preferences_id_seq OWNED BY public.user_notification_preferences.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255),
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    avatar character varying(255),
    is_admin boolean DEFAULT false NOT NULL
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: version_dialogue_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_dialogue_lines (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    game_version_id bigint NOT NULL,
    character_id bigint,
    iso_code character varying(10) NOT NULL,
    file_path character varying(255) NOT NULL,
    line_number integer NOT NULL,
    text_id bigint,
    context character varying(255)
);


--
-- Name: version_dialogue_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.version_dialogue_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: version_dialogue_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.version_dialogue_lines_id_seq OWNED BY public.version_dialogue_lines.id;


--
-- Name: version_file_categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_file_categories (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    game_version_id bigint NOT NULL,
    category character varying(20) NOT NULL,
    total_count integer NOT NULL,
    total_size bigint NOT NULL
);


--
-- Name: version_file_categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.version_file_categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: version_file_categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.version_file_categories_id_seq OWNED BY public.version_file_categories.id;


--
-- Name: version_file_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_file_types (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    version_file_category_id bigint NOT NULL,
    extension character varying(100) NOT NULL,
    count integer NOT NULL,
    size bigint NOT NULL
);


--
-- Name: version_file_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.version_file_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: version_file_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.version_file_types_id_seq OWNED BY public.version_file_types.id;


--
-- Name: version_language_stats; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_language_stats (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    game_version_id bigint NOT NULL,
    iso_code character varying(10) NOT NULL,
    blocks integer,
    words integer NOT NULL,
    menus integer,
    options integer
);


--
-- Name: version_language_stats_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.version_language_stats_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: version_language_stats_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.version_language_stats_id_seq OWNED BY public.version_language_stats.id;


--
-- Name: version_supported_languages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_supported_languages (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    game_version_id bigint NOT NULL,
    iso_code character varying(3) NOT NULL,
    is_available boolean DEFAULT true NOT NULL
);


--
-- Name: version_supported_languages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.version_supported_languages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: version_supported_languages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.version_supported_languages_id_seq OWNED BY public.version_supported_languages.id;


--
-- Name: vn_list_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vn_list_entries (
    id bigint NOT NULL,
    vn_list_id bigint NOT NULL,
    game_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    sort_order integer DEFAULT 0 NOT NULL,
    private_notes text
);


--
-- Name: vn_list_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vn_list_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vn_list_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vn_list_entries_id_seq OWNED BY public.vn_list_entries.id;


--
-- Name: vn_lists; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vn_lists (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    is_default boolean DEFAULT false NOT NULL,
    is_public boolean DEFAULT false NOT NULL,
    type character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: vn_lists_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vn_lists_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vn_lists_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vn_lists_id_seq OWNED BY public.vn_lists.id;


--
-- Name: characters id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.characters ALTER COLUMN id SET DEFAULT nextval('public.characters_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: game_game_jam id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_game_jam ALTER COLUMN id SET DEFAULT nextval('public.game_game_jam_id_seq'::regclass);


--
-- Name: game_jams id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_jams ALTER COLUMN id SET DEFAULT nextval('public.game_jams_id_seq'::regclass);


--
-- Name: game_versions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_versions ALTER COLUMN id SET DEFAULT nextval('public.game_versions_id_seq'::regclass);


--
-- Name: games id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.games ALTER COLUMN id SET DEFAULT nextval('public.games_id_seq'::regclass);


--
-- Name: import_states id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_states ALTER COLUMN id SET DEFAULT nextval('public.import_states_id_seq'::regclass);


--
-- Name: language_mappings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.language_mappings ALTER COLUMN id SET DEFAULT nextval('public.language_mappings_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: monitored_scheduled_task_log_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.monitored_scheduled_task_log_items ALTER COLUMN id SET DEFAULT nextval('public.monitored_scheduled_task_log_items_id_seq'::regclass);


--
-- Name: monitored_scheduled_tasks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.monitored_scheduled_tasks ALTER COLUMN id SET DEFAULT nextval('public.monitored_scheduled_tasks_id_seq'::regclass);


--
-- Name: notification_history id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_history ALTER COLUMN id SET DEFAULT nextval('public.notification_history_id_seq'::regclass);


--
-- Name: notification_queue id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_queue ALTER COLUMN id SET DEFAULT nextval('public.notification_queue_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: processed_events id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.processed_events ALTER COLUMN id SET DEFAULT nextval('public.processed_events_id_seq'::regclass);


--
-- Name: push_subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_subscriptions ALTER COLUMN id SET DEFAULT nextval('public.push_subscriptions_id_seq'::regclass);


--
-- Name: raters id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.raters ALTER COLUMN id SET DEFAULT nextval('public.raters_id_seq'::regclass);


--
-- Name: ratings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ratings ALTER COLUMN id SET DEFAULT nextval('public.ratings_id_seq'::regclass);


--
-- Name: social_accounts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.social_accounts ALTER COLUMN id SET DEFAULT nextval('public.social_accounts_id_seq'::regclass);


--
-- Name: unique_dialogue_texts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unique_dialogue_texts ALTER COLUMN id SET DEFAULT nextval('public.unique_dialogue_texts_id_seq'::regclass);


--
-- Name: user_game_progress id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_game_progress ALTER COLUMN id SET DEFAULT nextval('public.user_game_progress_id_seq'::regclass);


--
-- Name: user_notification_preferences id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_notification_preferences ALTER COLUMN id SET DEFAULT nextval('public.user_notification_preferences_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: version_character_stats id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_character_stats ALTER COLUMN id SET DEFAULT nextval('public.character_version_stats_id_seq'::regclass);


--
-- Name: version_dialogue_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_dialogue_lines ALTER COLUMN id SET DEFAULT nextval('public.version_dialogue_lines_id_seq'::regclass);


--
-- Name: version_file_categories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_file_categories ALTER COLUMN id SET DEFAULT nextval('public.version_file_categories_id_seq'::regclass);


--
-- Name: version_file_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_file_types ALTER COLUMN id SET DEFAULT nextval('public.version_file_types_id_seq'::regclass);


--
-- Name: version_language_stats id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_language_stats ALTER COLUMN id SET DEFAULT nextval('public.version_language_stats_id_seq'::regclass);


--
-- Name: version_supported_languages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_supported_languages ALTER COLUMN id SET DEFAULT nextval('public.version_supported_languages_id_seq'::regclass);


--
-- Name: vn_list_entries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vn_list_entries ALTER COLUMN id SET DEFAULT nextval('public.vn_list_entries_id_seq'::regclass);


--
-- Name: vn_lists id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vn_lists ALTER COLUMN id SET DEFAULT nextval('public.vn_lists_id_seq'::regclass);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: version_character_stats character_version_stats_game_version_id_character_id_iso_code_u; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_character_stats
    ADD CONSTRAINT character_version_stats_game_version_id_character_id_iso_code_u UNIQUE (game_version_id, character_id, iso_code);


--
-- Name: version_character_stats character_version_stats_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_character_stats
    ADD CONSTRAINT character_version_stats_pkey PRIMARY KEY (id);


--
-- Name: characters characters_game_id_character_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.characters
    ADD CONSTRAINT characters_game_id_character_id_unique UNIQUE (game_id, character_id);


--
-- Name: characters characters_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.characters
    ADD CONSTRAINT characters_pkey PRIMARY KEY (id);


--
-- Name: discord_users discord_users_pk; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_users
    ADD CONSTRAINT discord_users_pk PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: game_game_jam game_game_jam_game_id_game_jam_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_game_jam
    ADD CONSTRAINT game_game_jam_game_id_game_jam_id_unique UNIQUE (game_id, game_jam_id);


--
-- Name: game_game_jam game_game_jam_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_game_jam
    ADD CONSTRAINT game_game_jam_pkey PRIMARY KEY (id);


--
-- Name: game_jams game_jams_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_jams
    ADD CONSTRAINT game_jams_pkey PRIMARY KEY (id);


--
-- Name: game_jams game_jams_url_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_jams
    ADD CONSTRAINT game_jams_url_unique UNIQUE (url);


--
-- Name: game_versions game_versions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_versions
    ADD CONSTRAINT game_versions_pkey PRIMARY KEY (id);


--
-- Name: games games_game_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.games
    ADD CONSTRAINT games_game_id_unique UNIQUE (game_id);


--
-- Name: games games_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.games
    ADD CONSTRAINT games_pkey PRIMARY KEY (id);


--
-- Name: games games_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.games
    ADD CONSTRAINT games_slug_unique UNIQUE (slug);


--
-- Name: import_states import_states_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_states
    ADD CONSTRAINT import_states_pkey PRIMARY KEY (id);


--
-- Name: import_states import_states_type_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_states
    ADD CONSTRAINT import_states_type_unique UNIQUE (type);


--
-- Name: iso_639_3_languages iso_639_3_languages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.iso_639_3_languages
    ADD CONSTRAINT iso_639_3_languages_pkey PRIMARY KEY (id);


--
-- Name: language_mappings language_mappings_game_id_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.language_mappings
    ADD CONSTRAINT language_mappings_game_id_key_unique UNIQUE (game_id, game_language_key);


--
-- Name: language_mappings language_mappings_game_language_key_iso_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.language_mappings
    ADD CONSTRAINT language_mappings_game_language_key_iso_code_unique UNIQUE (game_language_key, iso_code);


--
-- Name: language_mappings language_mappings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.language_mappings
    ADD CONSTRAINT language_mappings_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: monitored_scheduled_task_log_items monitored_scheduled_task_log_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.monitored_scheduled_task_log_items
    ADD CONSTRAINT monitored_scheduled_task_log_items_pkey PRIMARY KEY (id);


--
-- Name: monitored_scheduled_tasks monitored_scheduled_tasks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.monitored_scheduled_tasks
    ADD CONSTRAINT monitored_scheduled_tasks_pkey PRIMARY KEY (id);


--
-- Name: notification_history notification_history_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_history
    ADD CONSTRAINT notification_history_pkey PRIMARY KEY (id);


--
-- Name: notification_queue notification_queue_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_queue
    ADD CONSTRAINT notification_queue_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: processed_events processed_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.processed_events
    ADD CONSTRAINT processed_events_pkey PRIMARY KEY (id);


--
-- Name: push_subscriptions push_subscriptions_endpoint_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_subscriptions
    ADD CONSTRAINT push_subscriptions_endpoint_unique UNIQUE (endpoint);


--
-- Name: push_subscriptions push_subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_subscriptions
    ADD CONSTRAINT push_subscriptions_pkey PRIMARY KEY (id);


--
-- Name: raters raters_alias_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.raters
    ADD CONSTRAINT raters_alias_unique UNIQUE (alias);


--
-- Name: raters raters_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.raters
    ADD CONSTRAINT raters_pkey PRIMARY KEY (id);


--
-- Name: raters raters_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.raters
    ADD CONSTRAINT raters_user_id_unique UNIQUE (user_id);


--
-- Name: ratings ratings_event_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ratings
    ADD CONSTRAINT ratings_event_id_unique UNIQUE (event_id);


--
-- Name: ratings ratings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ratings
    ADD CONSTRAINT ratings_pkey PRIMARY KEY (id);


--
-- Name: social_accounts social_accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.social_accounts
    ADD CONSTRAINT social_accounts_pkey PRIMARY KEY (id);


--
-- Name: social_accounts social_accounts_provider_name_provider_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.social_accounts
    ADD CONSTRAINT social_accounts_provider_name_provider_id_unique UNIQUE (provider_name, provider_id);


--
-- Name: unique_dialogue_texts unique_dialogue_texts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unique_dialogue_texts
    ADD CONSTRAINT unique_dialogue_texts_pkey PRIMARY KEY (id);


--
-- Name: unique_dialogue_texts unique_dialogue_texts_text_hash_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unique_dialogue_texts
    ADD CONSTRAINT unique_dialogue_texts_text_hash_unique UNIQUE (text_hash);


--
-- Name: user_game_progress user_game_progress_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_game_progress
    ADD CONSTRAINT user_game_progress_pkey PRIMARY KEY (id);


--
-- Name: user_game_progress user_game_progress_user_id_game_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_game_progress
    ADD CONSTRAINT user_game_progress_user_id_game_id_unique UNIQUE (user_id, game_id);


--
-- Name: user_notification_preferences user_notification_preferences_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_notification_preferences
    ADD CONSTRAINT user_notification_preferences_pkey PRIMARY KEY (id);


--
-- Name: user_notification_preferences user_notification_preferences_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_notification_preferences
    ADD CONSTRAINT user_notification_preferences_user_id_unique UNIQUE (user_id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: version_dialogue_lines version_dialogue_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_dialogue_lines
    ADD CONSTRAINT version_dialogue_lines_pkey PRIMARY KEY (id);


--
-- Name: version_file_categories version_file_categories_game_version_id_category_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_file_categories
    ADD CONSTRAINT version_file_categories_game_version_id_category_unique UNIQUE (game_version_id, category);


--
-- Name: version_file_categories version_file_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_file_categories
    ADD CONSTRAINT version_file_categories_pkey PRIMARY KEY (id);


--
-- Name: version_file_types version_file_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_file_types
    ADD CONSTRAINT version_file_types_pkey PRIMARY KEY (id);


--
-- Name: version_file_types version_file_types_version_file_category_id_extension_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_file_types
    ADD CONSTRAINT version_file_types_version_file_category_id_extension_unique UNIQUE (version_file_category_id, extension);


--
-- Name: version_language_stats version_language_stats_game_version_id_iso_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_language_stats
    ADD CONSTRAINT version_language_stats_game_version_id_iso_code_unique UNIQUE (game_version_id, iso_code);


--
-- Name: version_language_stats version_language_stats_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_language_stats
    ADD CONSTRAINT version_language_stats_pkey PRIMARY KEY (id);


--
-- Name: version_supported_languages version_supported_languages_game_version_id_iso_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_supported_languages
    ADD CONSTRAINT version_supported_languages_game_version_id_iso_code_unique UNIQUE (game_version_id, iso_code);


--
-- Name: version_supported_languages version_supported_languages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_supported_languages
    ADD CONSTRAINT version_supported_languages_pkey PRIMARY KEY (id);


--
-- Name: vn_list_entries vn_list_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vn_list_entries
    ADD CONSTRAINT vn_list_entries_pkey PRIMARY KEY (id);


--
-- Name: vn_list_entries vn_list_entries_vn_list_id_game_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vn_list_entries
    ADD CONSTRAINT vn_list_entries_vn_list_id_game_id_unique UNIQUE (vn_list_id, game_id);


--
-- Name: vn_lists vn_lists_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vn_lists
    ADD CONSTRAINT vn_lists_pkey PRIMARY KEY (id);


--
-- Name: vn_lists vn_lists_user_id_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vn_lists
    ADD CONSTRAINT vn_lists_user_id_name_unique UNIQUE (user_id, name);


--
-- Name: game_versions_game_id_is_latest_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX game_versions_game_id_is_latest_index ON public.game_versions USING btree (game_id, is_latest);


--
-- Name: games_authors_fulltext; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX games_authors_fulltext ON public.games USING gin (to_tsvector('english'::regconfig, (authors)::text));


--
-- Name: games_custom_tags_fulltext; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX games_custom_tags_fulltext ON public.games USING gin (to_tsvector('english'::regconfig, (custom_tags)::text));


--
-- Name: games_visible_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX games_visible_index ON public.games USING btree (is_visible);


--
-- Name: idx_game_versions_latest_lookup; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_game_versions_latest_lookup ON public.game_versions USING btree (is_latest, game_id, id);


--
-- Name: idx_processed_events_event_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_processed_events_event_id ON public.processed_events USING btree (event_id);


--
-- Name: idx_ratings_visible_only; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ratings_visible_only ON public.ratings USING btree (rater_id, rating) WHERE (is_visible = true);


--
-- Name: idx_ratings_visible_rater; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ratings_visible_rater ON public.ratings USING btree (is_visible, rater_id);


--
-- Name: idx_version_stats_version_lang; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_version_stats_version_lang ON public.version_language_stats USING btree (game_version_id, iso_code);


--
-- Name: notification_history_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_history_type_index ON public.notification_history USING btree (type);


--
-- Name: notification_history_user_id_game_id_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_history_user_id_game_id_type_index ON public.notification_history USING btree (user_id, game_id, type);


--
-- Name: notification_queue_channel_status_scheduled_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_queue_channel_status_scheduled_at_index ON public.notification_queue USING btree (channel, status, scheduled_at);


--
-- Name: notification_queue_user_id_channel_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_queue_user_id_channel_status_index ON public.notification_queue USING btree (user_id, channel, status);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: push_subscriptions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX push_subscriptions_user_id_index ON public.push_subscriptions USING btree (user_id);


--
-- Name: raters_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX raters_name_index ON public.raters USING btree (name);


--
-- Name: ratings_game_id_visible_has_review_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ratings_game_id_visible_has_review_index ON public.ratings USING btree (game_id, is_visible, is_reviewed);


--
-- Name: ratings_published_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ratings_published_at_index ON public.ratings USING btree (published_at);


--
-- Name: ratings_rater_id_game_id_published_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ratings_rater_id_game_id_published_at_index ON public.ratings USING btree (rater_id, game_id, published_at);


--
-- Name: ratings_rater_id_visible_has_review_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ratings_rater_id_visible_has_review_index ON public.ratings USING btree (rater_id, is_visible, is_reviewed);


--
-- Name: ratings_visible_has_review_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ratings_visible_has_review_index ON public.ratings USING btree (is_visible, is_reviewed);


--
-- Name: unique_dialogue_texts_search_vector_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX unique_dialogue_texts_search_vector_index ON public.unique_dialogue_texts USING btree (search_vector);


--
-- Name: version_dialogue_lines_character_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX version_dialogue_lines_character_id_index ON public.version_dialogue_lines USING btree (character_id);


--
-- Name: version_dialogue_lines_game_version_id_iso_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX version_dialogue_lines_game_version_id_iso_code_index ON public.version_dialogue_lines USING btree (game_version_id, iso_code);


--
-- Name: version_file_categories_category_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX version_file_categories_category_index ON public.version_file_categories USING btree (category);


--
-- Name: version_file_types_extension_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX version_file_types_extension_index ON public.version_file_types USING btree (extension);


--
-- Name: games update_game_slug_trigger; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER update_game_slug_trigger BEFORE INSERT OR UPDATE ON public.games FOR EACH ROW EXECUTE FUNCTION public.update_game_slug();


--
-- Name: game_versions update_game_version_latest_flag_trigger; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER update_game_version_latest_flag_trigger AFTER INSERT OR DELETE OR UPDATE OF published_at ON public.game_versions FOR EACH ROW EXECUTE FUNCTION public.update_game_version_latest_flag();


--
-- Name: version_character_stats character_version_stats_character_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_character_stats
    ADD CONSTRAINT character_version_stats_character_id_foreign FOREIGN KEY (character_id) REFERENCES public.characters(id) ON DELETE CASCADE;


--
-- Name: version_character_stats character_version_stats_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_character_stats
    ADD CONSTRAINT character_version_stats_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: characters characters_first_seen_in_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.characters
    ADD CONSTRAINT characters_first_seen_in_version_id_foreign FOREIGN KEY (first_seen_in_version_id) REFERENCES public.game_versions(id) ON DELETE SET NULL;


--
-- Name: characters characters_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.characters
    ADD CONSTRAINT characters_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: characters characters_last_seen_in_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.characters
    ADD CONSTRAINT characters_last_seen_in_version_id_foreign FOREIGN KEY (last_seen_in_version_id) REFERENCES public.game_versions(id) ON DELETE SET NULL;


--
-- Name: monitored_scheduled_task_log_items fk_scheduled_task_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.monitored_scheduled_task_log_items
    ADD CONSTRAINT fk_scheduled_task_id FOREIGN KEY (monitored_scheduled_task_id) REFERENCES public.monitored_scheduled_tasks(id) ON DELETE CASCADE;


--
-- Name: game_game_jam game_game_jam_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_game_jam
    ADD CONSTRAINT game_game_jam_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: game_game_jam game_game_jam_game_jam_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_game_jam
    ADD CONSTRAINT game_game_jam_game_jam_id_foreign FOREIGN KEY (game_jam_id) REFERENCES public.game_jams(id) ON DELETE CASCADE;


--
-- Name: game_versions game_versions_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_versions
    ADD CONSTRAINT game_versions_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id);


--
-- Name: games games_source_language_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.games
    ADD CONSTRAINT games_source_language_id_foreign FOREIGN KEY (source_language_id) REFERENCES public.iso_639_3_languages(id) ON DELETE SET NULL;


--
-- Name: language_mappings language_mappings_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.language_mappings
    ADD CONSTRAINT language_mappings_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: notification_history notification_history_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_history
    ADD CONSTRAINT notification_history_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: notification_history notification_history_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_history
    ADD CONSTRAINT notification_history_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: notification_history notification_history_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_history
    ADD CONSTRAINT notification_history_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: notification_queue notification_queue_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_queue
    ADD CONSTRAINT notification_queue_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: notification_queue notification_queue_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_queue
    ADD CONSTRAINT notification_queue_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: notification_queue notification_queue_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_queue
    ADD CONSTRAINT notification_queue_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: push_subscriptions push_subscriptions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_subscriptions
    ADD CONSTRAINT push_subscriptions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: ratings ratings_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ratings
    ADD CONSTRAINT ratings_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id);


--
-- Name: ratings ratings_rater_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ratings
    ADD CONSTRAINT ratings_rater_id_foreign FOREIGN KEY (rater_id) REFERENCES public.raters(id);


--
-- Name: social_accounts social_accounts_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.social_accounts
    ADD CONSTRAINT social_accounts_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_game_progress user_game_progress_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_game_progress
    ADD CONSTRAINT user_game_progress_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: user_game_progress user_game_progress_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_game_progress
    ADD CONSTRAINT user_game_progress_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE SET NULL;


--
-- Name: user_game_progress user_game_progress_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_game_progress
    ADD CONSTRAINT user_game_progress_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_notification_preferences user_notification_preferences_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_notification_preferences
    ADD CONSTRAINT user_notification_preferences_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: version_dialogue_lines version_dialogue_lines_character_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_dialogue_lines
    ADD CONSTRAINT version_dialogue_lines_character_id_foreign FOREIGN KEY (character_id) REFERENCES public.characters(id) ON DELETE SET NULL;


--
-- Name: version_dialogue_lines version_dialogue_lines_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_dialogue_lines
    ADD CONSTRAINT version_dialogue_lines_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: version_file_categories version_file_categories_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_file_categories
    ADD CONSTRAINT version_file_categories_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: version_file_types version_file_types_version_file_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_file_types
    ADD CONSTRAINT version_file_types_version_file_category_id_foreign FOREIGN KEY (version_file_category_id) REFERENCES public.version_file_categories(id) ON DELETE CASCADE;


--
-- Name: version_language_stats version_language_stats_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_language_stats
    ADD CONSTRAINT version_language_stats_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: version_supported_languages version_supported_languages_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_supported_languages
    ADD CONSTRAINT version_supported_languages_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: version_supported_languages version_supported_languages_iso_code_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_supported_languages
    ADD CONSTRAINT version_supported_languages_iso_code_foreign FOREIGN KEY (iso_code) REFERENCES public.iso_639_3_languages(id) ON DELETE RESTRICT;


--
-- Name: vn_list_entries vn_list_entries_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vn_list_entries
    ADD CONSTRAINT vn_list_entries_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: vn_list_entries vn_list_entries_vn_list_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vn_list_entries
    ADD CONSTRAINT vn_list_entries_vn_list_id_foreign FOREIGN KEY (vn_list_id) REFERENCES public.vn_lists(id) ON DELETE CASCADE;


--
-- Name: vn_lists vn_lists_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vn_lists
    ADD CONSTRAINT vn_lists_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

--
-- PostgreSQL database dump
--

-- Dumped from database version 17.4 (Debian 17.4-1.pgdg120+2)
-- Dumped by pg_dump version 17.4 (Debian 17.4-1.pgdg120+2)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	2014_10_12_000000_create_users_table	1
2	2014_10_12_100000_create_password_reset_tokens_table	1
3	2019_08_19_000000_create_failed_jobs_table	1
4	2019_12_14_000001_create_personal_access_tokens_table	1
5	2024_03_01_000636_create_games_table	1
6	2024_03_01_000706_create_raters_table	1
7	2024_03_01_000722_create_ratings_table	1
8	2024_03_01_004125_create_game_versions_table	1
9	2024_05_12_211909_modify_raters_table	2
10	2024_05_12_211954_create_game_rater_table	2
11	2024_05_13_200336_drop_game_rater_table	3
12	2024_05_13_200810_modify_games_table	3
13	2024_05_18_233635_modify_games_table	4
14	2024_07_25_193937_add_uploads_column_to_games_table	5
15	2024_12_28_001000_create_iso_639_3_languages_table	6
16	2024_12_28_001100_create_language_mappings_table	6
17	2024_12_28_001200_create_version_language_stats_table	6
18	2024_12_28_001300_create_version_character_stats_table	6
19	2024_12_28_114217_create_game_supported_languages_table	6
20	2024_12_28_114259_migrate_version_stats	6
21	2024_12_28_114327_modify_games_table	6
22	2024_12_28_150226_rename_boolean_columns	6
23	2024_12_28_233201_add_is_latest_to_game_versions_table	6
24	2024_12_28_234040_add_indices_to_version_tables	6
25	2025_01_05_174709_add_slug_to_games_table	6
26	2025_01_05_230049_drop_stats_columns_from_game_versions_table	6
27	2025_01_06_105417_create_processed_events_table	7
28	2025_01_06_130710_add_is_feedless_to_games_table	8
29	2025_01_06_134332_drop_duplicate_columns_from_games_table	8
30	2025_01_10_172021_add_slug_trigger_for_games_table	9
31	2025_01_10_220426_add_source_language_to_games_table	10
32	2025_01_10_221901_drop_game_supported_languages_table	10
33	2025_01_17_111449_set_default_values_in_database	11
34	2025_01_17_182345_add_default_columns_to_processed_events_table	12
35	2025_01_17_190458_remove_processed_at_column_from_processed_events_table	12
36	2025_01_19_163611_add_default_columns_to_discord_users_table	13
37	2025_01_19_201905_migrate_version_character_stats	14
38	2025_01_21_185728_add_display_name_corrections_to_character_table	15
39	2025_01_28_220853_create_version_supported_languages_table	16
40	2025_01_29_222525_add_index_to_ratings_table	17
41	2025_01_24_135236_add_file_stats_tables	18
42	2025_02_02_000804_increase_extension_column_length_in_version_file_types_table	19
43	2025_02_02_180621_create_schedule_monitor_tables	20
44	2025_02_11_234533_add_optimized_thumbnails_to_games_table	21
45	2025_02_15_103117_add_alias_column_to_raters_table	22
46	2025_02_16_143350_add_weight_to_raters_table	23
47	2025_02_16_180625_add_scores_to_games_table	23
48	2025_02_16_191716_add_suspicious_columns_to_raters_table	23
49	2025_02_16_231617_create_cache_table	24
50	2025_02_16_234103_add_indices_to_ratings_and_raters_tables	25
51	2025_02_17_010819_add_raw_weighted_rating_to_games_table	26
52	2025_03_02_001118_create_version_dialogue_lines_table	27
53	2025_03_23_100103_add_game_id_column_to_language_mappings_table	28
54	2025_03_23_124716_add_is_available_to_version_supported_languages_table	29
55	2024_03_21_000001_create_social_accounts_table	30
56	2025_03_21_121537_nullable_email_column_in_users_table	30
57	2025_03_21_163104_add_avatar_column_to_users_table	30
58	2025_03_23_000645_drop_weighted_scores_columns	30
59	2024_03_22_000001_create_vn_lists_table	31
60	2024_03_22_000002_create_vn_list_entries_table	31
61	2024_03_25_000001_remove_rating_from_vn_list_entries	31
62	2024_03_25_000002_add_sort_order_to_vn_list_entries	31
63	2025_03_23_194354_create_user_game_progress_and_updates	31
64	2025_03_25_000001_create_update_notifications_table	32
65	2025_03_26_000001_create_user_notification_preferences_table	32
66	2025_03_26_221629_add_notification_digest_to_user_notification_preferences	32
67	2025_04_03_000001_create_push_subscriptions_table	33
68	2025_04_03_000002_create_notification_queue_table	33
69	2025_04_03_000003_add_receive_updates_to_user_game_progress	33
70	2025_04_04_172600_add_meta_data_to_notification_queue	34
71	2024_03_26_000001_create_import_states_table	35
72	2025_04_06_172154_add_is_admin_to_users_table	36
73	2025_04_06_181624_change_avatar_column_type_in_users_table	36
74	2025_05_01_000000_add_additional_game_details	36
75	2025_05_01_000001_create_game_jams_table	36
76	2025_05_01_000002_add_needs_details_fetch_to_game_jams	36
77	2025_04_09_231946_add_custom_css_to_games_table	37
78	2025_04_13_005722_remove_columns_from_game_jams_table	38
79	2025_05_02_000001_add_criteria_rankings_to_game_game_jam_table	39
80	2025_04_13_163436_add_criteria_rankings_to_game_game_jam_table	40
81	2025_04_13_190609_add_private_notes_to_vn_list_entries	41
82	2025_05_15_000000_add_paid_and_demo_columns_to_games_table	42
83	2025_04_14_190104_remove_suggested_price_from_games_table	43
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 83, true);


--
-- PostgreSQL database dump complete
--

