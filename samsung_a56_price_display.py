#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
نمایش قیمت‌های Samsung Galaxy A56 5G از نتایج جستجوی Google
بر اساس اطلاعات استخراج شده از torob.com
"""

def display_samsung_a56_prices():
    print("="*60)
    print("📱 قیمت‌های Samsung Galaxy A56 5G در ایران")
    print("="*60)
    print()
    
    # قیمت‌های استخراج شده از torob.com
    print("🔍 نتایج جستجو از torob.com:")
    print("-" * 40)
    
    # مدل 128GB/8GB
    print("📦 Samsung Galaxy A56 5G - 128GB/8GB RAM:")
    print("   💰 ارزان‌ترین قیمت: ۳۲,۷۵۰,۰۰۰ تومان")
    print("   🏪 منبع: torob.com")
    print()
    
    # مدل 256GB/12GB
    print("📦 Samsung Galaxy A56 5G - 256GB/12GB RAM:")
    print("   💰 ارزان‌ترین قیمت: ۳۶,۲۳۰,۰۰۰ تومان")
    print("   🏪 منبع: torob.com")
    print()
    
    # تبدیل به ریال
    print("💱 تبدیل به ریال:")
    print("-" * 40)
    print(f"   128GB/8GB: {32_750_000 * 10:,} ریال")
    print(f"   256GB/12GB: {36_230_000 * 10:,} ریال")
    print()
    
    # رنج قیمت کلی
    print("📊 رنج قیمت کلی:")
    print("-" * 40)
    print("   🔻 پایین‌ترین قیمت: ۳۲,۷۵۰,۰۰۰ تومان")
    print("   🔺 بالاترین قیمت: ۳۶,۲۳۰,۰۰۰ تومان")
    print()
    
    # توضیحات
    print("ℹ️  توضیحات:")
    print("-" * 40)
    print("   • قیمت‌ها از جستجوی Google در سایت torob.com استخراج شده")
    print("   • مدل 256GB/8GB در نتایج مشاهده نشد")
    print("   • قیمت‌ها ممکن است تغییر کنند")
    print("   • برای آخرین قیمت‌ها به torob.com مراجعه کنید")
    print()
    
    print("="*60)
    print("🔗 لینک جستجو: گوشی سامسونگ A56 5G | حافظه 256 رم 8 گیگابایت site:torob.com")
    print("="*60)

if __name__ == "__main__":
    display_samsung_a56_prices()