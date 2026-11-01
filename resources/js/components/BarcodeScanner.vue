<template>
  <div class="card border-success-subtle">
    <div class="card-header d-flex justify-content-between align-items-center py-2"><strong class="small"><i class="bi bi-camera me-1"></i>Camera barcode scanner</strong><button class="btn-close" @click="$emit('close')"></button></div>
    <div class="card-body p-2">
      <video ref="video" autoplay muted playsinline class="w-100 rounded" style="max-height:240px;object-fit:cover;background:#111"></video>
      <div class="small text-muted mt-2">Align the barcode inside the camera frame. Camera access requires HTTPS or localhost and a supported browser.</div>
      <div v-if="error" class="alert alert-warning py-2 mt-2 mb-0 small">{{ error }} Use a USB barcode scanner or type the barcode instead.</div>
    </div>
  </div>
</template>
<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
const emit=defineEmits(['detected','close']); const video=ref(null); const error=ref(''); let stream=null; let frame=0; let detector=null; let active=true;
async function scan(){if(!active||!detector||!video.value)return;try{if(video.value.readyState>=2){const hits=await detector.detect(video.value);if(hits?.length){active=false;emit('detected',hits[0].rawValue);return;}}}catch(e){error.value='Unable to read this barcode. Try a clearer view.';}frame=requestAnimationFrame(scan);}
onMounted(async()=>{try{if(!('BarcodeDetector' in window))throw new Error('Camera barcode detection is not supported in this browser.');detector=new window.BarcodeDetector({formats:await window.BarcodeDetector.getSupportedFormats()});stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false});video.value.srcObject=stream;await video.value.play();scan();}catch(e){error.value=e.message||'Camera permission was denied.';}});
onBeforeUnmount(()=>{active=false;cancelAnimationFrame(frame);stream?.getTracks().forEach(t=>t.stop());});
</script>
