-- Migration Script: Convert age field to date_of_birth
-- Run this script to migrate existing data from age to date_of_birth
-- 迁移脚本：将age字段转换为date_of_birth字段
-- 在现有数据库上运行此脚本以迁移数据

-- Step 1: Add the new date_of_birth column (if not already added)
-- 步骤1：添加新的date_of_birth字段（如果尚未添加）
ALTER TABLE person ADD COLUMN date_of_birth DATE AFTER gender;

-- Step 2: Migrate existing age data to date_of_birth
-- Calculate approximate birth date based on current age
-- Note: This calculates birth date as January 1st of the birth year
-- 步骤2：将现有age数据迁移到date_of_birth
-- 根据当前年龄计算大约的出生日期
-- 注意：这将计算出生年份的1月1日作为出生日期
UPDATE person 
SET date_of_birth = DATE_SUB(CURDATE(), INTERVAL age YEAR)
WHERE age IS NOT NULL AND date_of_birth IS NULL;

-- Step 3: Drop the age column and its constraint (optional, run after verification)
-- 步骤3：删除age字段及其约束（可选，在验证后运行）
-- WARNING: Only run this after verifying the migration was successful!
-- 警告：仅在验证迁移成功后运行此命令！

-- First, drop the constraint
-- ALTER TABLE person DROP CONSTRAINT age_check;

-- Then, drop the column
-- ALTER TABLE person DROP COLUMN age;

-- Verification Query: Check the migration results
-- 验证查询：检查迁移结果
-- SELECT person_id, name, age, date_of_birth, TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) AS calculated_age FROM person;
