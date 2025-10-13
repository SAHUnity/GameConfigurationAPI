-- Test data for Game Configuration API
-- Run this after creating the database schema

-- Insert sample games with properly hashed API keys
INSERT INTO games (name, game_id, api_key, description, status) VALUES 
('Space Adventure', 'space_adventure_001', 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6', 'A space exploration game with multiplayer features', 'active'),
('Fantasy Quest', 'fantasy_quest_001', 'f1e2d3c4b5a6f1e2d3c4b5a6f1e2d3c4b5a6f1e2d3c4b5a6f1e2d3c4b5a6f1e2d3c4b5a6', 'An RPG game with magic and dragons', 'active'),
('Racing Pro', 'racing_pro_001', 'r1a2c3i4n5g6r1a2c3i4n5g6r1a2c3i4n5g6r1a2c3i4n5g6r1a2c3i4n5g6r1a2c3i4n5g6', 'High-speed racing game', 'inactive');

-- Insert sample configurations for Space Adventure
INSERT INTO configurations (game_id, config_key, config_value, data_type, category, description) VALUES
(1, 'state.maintenanceMode', 'false', 'boolean', 'state', 'Whether the game is in maintenance mode'),
(1, 'state.pvpEnabled', 'true', 'boolean', 'state', 'Whether PvP is enabled'),
(1, 'state.chatEnabled', 'true', 'boolean', 'state', 'Whether chat is enabled'),
(1, 'config.maxPlayersPerMatch', '8', 'number', 'config', 'Maximum players per match'),
(1, 'config.experienceMultiplier', '1.5', 'number', 'config', 'Experience gain multiplier'),
(1, 'config.energyRegenMinutes', '5', 'number', 'config', 'Minutes to regenerate one energy'),
(1, 'config.shopDiscountPercent', '15', 'number', 'config', 'Shop discount percentage'),
(1, 'config.dailyRewardEnabled', 'true', 'boolean', 'config', 'Whether daily rewards are enabled'),
(1, 'config.newPlayerProtectionDays', '3', 'number', 'config', 'Days of protection for new players'),
(1, 'config.maxFriends', '50', 'number', 'config', 'Maximum number of friends'),
(1, 'config.chatMessageMaxLength', '150', 'number', 'config', 'Maximum chat message length'),
(1, 'list.enabledLevels', '["level_1", "level_2", "level_3", "boss_space_station"]', 'array', 'list', 'List of enabled levels'),
(1, 'list.disabledItems', '["broken_laser", "debug_shield"]', 'array', 'list', 'List of disabled items'),
(1, 'list.availableCharacters', ["pilot", "engineer", "scientist", "soldier"]', 'array', 'list', 'Available character classes'),
(1, 'list.featuredContent', ["new_ship_pack", "special_weapon"]', 'array', 'list', 'Featured content in shop'),
(1, 'data.economyRates', '{"gold_to_gems": 100, "gems_to_premium": 10, "energy_cost_per_match": 1}', 'object', 'data', 'Economy conversion rates'),
(1, 'data.regionSettings', '{"allowedCountries": ["US", "CA", "UK", "DE"], "restrictedFeatures": {"CN": ["chat", "social"], "KR": ["lootbox"]}}', 'object', 'data', 'Region-specific settings'),
(1, 'data.priceOverrides', '{"premium_ship": 499, "boost_pack": 149, "vip_subscription": 999}', 'object', 'data', 'Price overrides for items'),
(1, 'meta.apiVersion', '1.2.0', 'string', 'meta', 'API version'),
(1, 'meta.configVersion', '2025.01.15', 'string', 'meta', 'Configuration version'),
(1, 'meta.supportedClientVersions', '["1.0.0", "1.1.0", "1.2.0"]', 'array', 'meta', 'Supported client versions'),
(1, 'meta.maintenanceSchedule', '2025-12-15T02:00:00Z', 'string', 'meta', 'Next maintenance schedule'),
(1, 'meta.featureRollout', '{"newUI": 0.1, "advancedCrafting": 0.05}', 'object', 'meta', 'Feature rollout percentages'),
(1, 'meta.analyticsConfig', '{"enabledEvents": ["level_complete", "purchase", "login"], "samplingRate": 0.1}', 'object', 'meta', 'Analytics configuration');

-- Insert sample configurations for Fantasy Quest
INSERT INTO configurations (game_id, config_key, config_value, data_type, category, description) VALUES
(2, 'state.maintenanceMode', 'false', 'boolean', 'state', 'Whether the game is in maintenance mode'),
(2, 'state.pvpEnabled', 'false', 'boolean', 'state', 'Whether PvP is enabled'),
(2, 'state.chatEnabled', 'true', 'boolean', 'state', 'Whether chat is enabled'),
(2, 'state.newRegistrationsOpen', 'true', 'boolean', 'state', 'Whether new registrations are open'),
(2, 'config.maxPlayersPerParty', '4', 'number', 'config', 'Maximum players per party'),
(2, 'config.experienceMultiplier', '2.0', 'number', 'config', 'Experience gain multiplier'),
(2, 'config.energyRegenMinutes', '3', 'number', 'config', 'Minutes to regenerate one energy'),
(2, 'config.shopDiscountPercent', '25', 'number', 'config', 'Shop discount percentage'),
(2, 'config.dailyRewardEnabled', 'true', 'boolean', 'config', 'Whether daily rewards are enabled'),
(2, 'config.newPlayerProtectionDays', '7', 'number', 'config', 'Days of protection for new players'),
(2, 'config.maxGuildMembers', '100', 'number', 'config', 'Maximum guild members'),
(2, 'config.chatMessageMaxLength', '200', 'number', 'config', 'Maximum chat message length'),
(2, 'list.enabledLevels', '["forest_1", "cave_1", "castle_1", "dragon_lair"]', 'array', 'list', 'List of enabled levels'),
(2, 'list.disabledItems', ["broken_sword", "debug_armor"]', 'array', 'list', 'List of disabled items'),
(2, 'list.availableCharacters', ["warrior", "mage", "archer", "healer"]', 'array', 'list', 'Available character classes'),
(2, 'list.bannedPlayers', ["cheater123", "spammer456"]', 'array', 'list', 'List of banned players'),
(2, 'list.whitelistedBetaTesters', ["tester001", "dev002"]', 'array', 'list', 'Beta tester whitelist'),
(2, 'data.economyRates', '{"gold_to_gems": 50, "gems_to_premium": 5, "energy_cost_per_dungeon": 1}', 'object', 'data', 'Economy conversion rates'),
(2, 'data.regionSettings', '{"allowedCountries": ["US", "CA", "UK", "EU"], "restrictedFeatures": {"CN": ["chat", "guild"], "JP": ["pvp"]}}', 'object', 'data', 'Region-specific settings'),
(2, 'data.priceOverrides', '{"premium_weapon": 299, "boost_pack": 99, "vip_subscription": 599}', 'object', 'data', 'Price overrides for items'),
(2, 'meta.apiVersion', '1.1.0', 'string', 'meta', 'API version'),
(2, 'meta.configVersion', '2025.01.10', 'string', 'meta', 'Configuration version'),
(2, 'meta.supportedClientVersions', '["1.0.0", "1.1.0"]', 'array', 'meta', 'Supported client versions'),
(2, 'meta.maintenanceSchedule', '2025-12-10T03:00:00Z', 'string', 'meta', 'Next maintenance schedule'),
(2, 'meta.featureRollout', '{"newUI": 0.2, "advancedCrafting": 0.1}', 'object', 'meta', 'Feature rollout percentages'),
(2, 'meta.analyticsConfig', '{"enabledEvents": ["level_complete", "purchase", "login", "guild_join"], "samplingRate": 0.2}', 'object', 'meta', 'Analytics configuration');

-- Insert sample configurations for Racing Pro
INSERT INTO configurations (game_id, config_key, config_value, data_type, category, description) VALUES
(3, 'state.maintenanceMode', 'true', 'boolean', 'state', 'Whether the game is in maintenance mode'),
(3, 'state.pvpEnabled', 'true', 'boolean', 'state', 'Whether PvP is enabled'),
(3, 'state.chatEnabled', 'false', 'boolean', 'state', 'Whether chat is enabled'),
(3, 'config.maxPlayersPerRace', '12', 'number', 'config', 'Maximum players per race'),
(3, 'config.experienceMultiplier', '1.0', 'number', 'config', 'Experience gain multiplier'),
(3, 'config.energyRegenMinutes', '10', 'number', 'config', 'Minutes to regenerate one energy'),
(3, 'config.shopDiscountPercent', '10', 'number', 'config', 'Shop discount percentage'),
(3, 'config.dailyRewardEnabled', 'false', 'boolean', 'config', 'Whether daily rewards are enabled'),
(3, 'config.newPlayerProtectionDays', '1', 'number', 'config', 'Days of protection for new players'),
(3, 'config.maxFriends', '25', 'number', 'config', 'Maximum number of friends'),
(3, 'config.chatMessageMaxLength', '100', 'number', 'config', 'Maximum chat message length'),
(3, 'list.enabledTracks', ["speedway", "city", "mountain", "desert"]', 'array', 'list', 'List of enabled tracks'),
(3, 'list.disabledCars', ["debug_car", "prototype_vehicle"]', 'array', 'list', 'List of disabled cars'),
(3, 'list.availableCars', ["sports_car", "racing_car", "truck", "motorcycle"]', 'array', 'list', 'Available car types'),
(3, 'list.featuredContent', ["new_track_pack", "special_car"]', 'array', 'list', 'Featured content in shop'),
(3, 'data.economyRates', '{"gold_to_gems": 200, "gems_to_premium": 20, "energy_cost_per_race": 2}', 'object', 'data', 'Economy conversion rates'),
(3, 'data.regionSettings', '{"allowedCountries": ["US", "CA", "UK"], "restrictedFeatures": {"CN": ["multiplayer"], "KR": ["shop"]}}', 'object', 'data', 'Region-specific settings'),
(3, 'data.priceOverrides', '{"premium_car": 799, "boost_pack": 199, "vip_subscription": 1299}', 'object', 'data', 'Price overrides for items'),
(3, 'meta.apiVersion', '1.0.0', 'string', 'meta', 'API version'),
(3, 'meta.configVersion', '2025.01.05', 'string', 'meta', 'Configuration version'),
(3, 'meta.supportedClientVersions', '["1.0.0"]', 'array', 'meta', 'Supported client versions'),
(3, 'meta.maintenanceSchedule', '2025-12-20T01:00:00Z', 'string', 'meta', 'Next maintenance schedule'),
(3, 'meta.featureRollout', '{"newUI": 0.05, "advancedPhysics": 0.02}', 'object', 'meta', 'Feature rollout percentages'),
(3, 'meta.analyticsConfig', '{"enabledEvents": ["race_complete", "purchase", "login"], "samplingRate": 0.05}', 'object', 'meta', 'Analytics configuration');

-- Note: The API keys used here are test keys for development purposes
-- For production, generate new API keys through the admin panel

-- Test API keys for Unity client testing:
-- test_api_key_1 for Space Adventure
-- test_api_key_2 for Fantasy Quest  
-- test_api_key_3 for Racing Pro